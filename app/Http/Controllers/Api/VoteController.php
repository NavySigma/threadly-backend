<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Comment;
use App\Models\Post;
use App\Models\User;
use App\Models\Vote;
use App\Services\NotificationService;
use App\Services\PointService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VoteController extends Controller
{
    public function __construct(
        private PointService $pointService,
        private NotificationService $notificationService
    ) {}

    private function resolveTarget(string $type, string $id): Post|Comment
    {
        return match($type) {
            'post'    => Post::findOrFail($id),
            'comment' => Comment::findOrFail($id),
        };
    }

    public function vote(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'target_type' => 'required|in:post,comment',
            'target_id'   => 'required|uuid',
            'vote_type'   => 'required|in:upvote,downvote',
        ]);

        $user   = $request->user();
        $target = $this->resolveTarget($validated['target_type'], $validated['target_id']);

        if ($validated['target_type'] === 'comment' && !is_null($target->parent_id)) {
            return response()->json(['message' => 'Reply tidak bisa di-vote.'], 422);
        }

        // Kalau vote ke comment, cek post-nya
            if ($validated['target_type'] === 'comment') {
                $post = Post::findOrFail($target->post_id);
                if (!$post->isAccessible()) {
                    return response()->json(['message' => 'Post sudah ditutup.'], 422);
                }
            }

            // Kalau vote ke post langsung
            if ($validated['target_type'] === 'post' && !$target->isAccessible()) {
                return response()->json(['message' => 'Post sudah ditutup.'], 422);
            }

        // Tidak bisa vote konten sendiri
        if ($target->user_id === $user->id) {
            return response()->json(['message' => 'Tidak bisa vote konten sendiri.'], 422);
        }

        // Minimal 15 poin untuk downvote
        if ($validated['vote_type'] === 'downvote' && $user->reputation_points < 15) {
            return response()->json(['message' => 'Minimal 15 poin untuk downvote.'], 422);
        }

        $owner = User::findOrFail($target->user_id);

        $existingVote = Vote::where('user_id', $user->id)
            ->where('target_id', $validated['target_id'])
            ->where('target_type', $validated['target_type'])
            ->first();

        // Sudah vote sebelumnya
        if ($existingVote) {
            // Vote type sama → toggle off (unvote)
            if ($existingVote->vote_type === $validated['vote_type']) {
                $this->reversePoints($user, $owner, $existingVote->vote_type, $target->id);
                $target->increment('vote_score', $existingVote->vote_type === 'upvote' ? -1 : 1);
                $existingVote->delete();

                return response()->json(['message' => 'Vote dibatalkan.']);
            }

            // Vote type berbeda → ganti vote
            $this->reversePoints($user, $owner, $existingVote->vote_type, $target->id);
            $target->increment('vote_score', $existingVote->vote_type === 'upvote' ? -1 : 1);

            $existingVote->update(['vote_type' => $validated['vote_type']]);

            $this->applyPoints($user, $owner, $validated['vote_type'], $target->id);
            $target->increment('vote_score', $validated['vote_type'] === 'upvote' ? 1 : -1);

            if ($validated['target_type'] === 'post') {
                cache()->forget("posts.show.{$validated['target_id']}");
            }
            return response()->json(['message' => 'Vote berhasil diubah.']);
        }

        // Vote baru
        Vote::create([
            'user_id'     => $user->id,
            'target_id'   => $validated['target_id'],
            'target_type' => $validated['target_type'],
            'vote_type'   => $validated['vote_type'],
        ]);

        if ($validated['vote_type'] === 'upvote') {
            $this->notificationService->send(
                recipient    : $owner,
                actor        : $user,
                type         : 'upvote',
                referenceId  : $target->id,
                referenceType: $validated['target_type'],
            );
        }

        $this->applyPoints($user, $owner, $validated['vote_type'], $target->id);
        $target->increment('vote_score', $validated['vote_type'] === 'upvote' ? 1 : -1);

        return response()->json(['message' => 'Vote berhasil.'], 201);
    }

    private function applyPoints(User $voter, User $owner, string $voteType, string $referenceId): void
    {
        if ($voteType === 'upvote') {
            // Voter: tidak ada perubahan
            // Owner: +2
            $this->pointService->adjust($owner, 2, 'content_upvoted', $referenceId, 'Konten kamu di-upvote');
        } else {
            // Voter: -1
            // Owner: -2
            $this->pointService->adjust($voter, -1, 'downvote_given', $referenceId, 'Kamu mendownvote konten orang lain');
            $this->pointService->adjust($owner, -2, 'content_downvoted', $referenceId, 'Konten kamu di-downvote');
        }
    }

    private function reversePoints(User $voter, User $owner, string $voteType, string $referenceId): void
    {
        if ($voteType === 'upvote') {
            // Reverse upvote → owner -2
            $this->pointService->adjust($owner, -2, 'upvote_removed', $referenceId, 'Upvote pada konten kamu dibatalkan');
        } else {
            // Reverse downvote → voter +1, owner +2
            $this->pointService->adjust($voter, 1, 'downvote_removed', $referenceId, 'Downvote yang kamu berikan dibatalkan');
            $this->pointService->adjust($owner, 2, 'downvote_removed', $referenceId, 'Downvote pada konten kamu dibatalkan');
        }
    }
}
