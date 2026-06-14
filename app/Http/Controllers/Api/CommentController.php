<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Comment;
use App\Models\CommentEditHistory;
use App\Models\Like;
use App\Models\Post;
use App\Models\User;
use App\Models\Vote;
use App\Services\NotificationService;
use App\Services\PointService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CommentController extends Controller
{
    public function __construct(
        private PointService $pointService,
        private NotificationService $notificationService,
    ) {}
    // List komentar dari sebuah post (top-level only, reply di-load nested)
    public function index(Request $request, Post $post): JsonResponse
    {
        $comments = Comment::with([
                'user:id,username,avatar_url,reputation_points',
                'replies.user:id,username,avatar_url,reputation_points',
            ])
            ->withCount('likes')
            ->where('post_id', $post->id)
            ->whereNull('parent_id')
            ->latest()
            ->paginate(20);

        $allComments = collect($comments->items())
            ->flatMap(fn ($c) => collect([$c])->concat($c->replies));

        $allCommentIds = $allComments->pluck('id');

        // Bulk load likes_count for replies (top-level already has withCount)
        $replyLikeCounts = Like::whereIn('target_id', $allCommentIds)
            ->where('target_type', 'comment')
            ->selectRaw('target_id, count(*) as count')
            ->groupBy('target_id')
            ->pluck('count', 'target_id');

        $userLikedIds = collect();
        $userVotes = collect();
        $currentUser = $request->user('sanctum');
        if ($currentUser) {
            $userId = $currentUser->id;

            $userLikedIds = Like::where('target_type', 'comment')
                ->whereIn('target_id', $allCommentIds)
                ->where('user_id', $userId)
                ->pluck('target_id');

            $userVotes = Vote::where('target_type', 'comment')
                ->whereIn('target_id', $allCommentIds)
                ->where('user_id', $userId)
                ->pluck('vote_type', 'target_id');
        }
        $userLikedSet = $userLikedIds->flip();

        // Bulk load edit counts
        $editCounts = CommentEditHistory::whereIn('comment_id', $allCommentIds)
            ->selectRaw('comment_id, count(*) as count')
            ->groupBy('comment_id')
            ->pluck('count', 'comment_id');

        $comments->through(function ($comment) use ($userLikedSet, $replyLikeCounts, $userVotes, $editCounts) {
            $comment->user_liked = $userLikedSet->has($comment->id);
            $comment->user_vote = $userVotes->get($comment->id);
            $comment->edits_count = $editCounts->get($comment->id, 0);

            $comment->replies->each(function ($reply) use ($userLikedSet, $replyLikeCounts, $userVotes, $editCounts) {
                $reply->user_liked = $userLikedSet->has($reply->id);
                $reply->likes_count = $replyLikeCounts->get($reply->id, 0);
                $reply->user_vote = $userVotes->get($reply->id);
                $reply->edits_count = $editCounts->get($reply->id, 0);
            });

            return $comment;
        });

        return response()->json($comments);
    }

    // Buat komentar atau reply
    public function store(Request $request, Post $post): JsonResponse
    {
        if ($request->user()->is_banned) {
            return response()->json(['message' => 'Akun kamu telah diblokir.'], 403);
        }

        if (!$post->isAccessible()) {
            return response()->json(['message' => 'Post tidak tersedia untuk dikomentari.'], 422);
        }

        $validated = $request->validate([
            'body'      => 'required|string|min:5',
            'parent_id' => 'nullable|uuid|exists:comments,id',
        ]);

        $user = $request->user();

        // Kalau bukan reply, cek limit comment owner
        if (empty($validated['parent_id']) && $user->id === $post->user_id) {
            $ownerCommentCount = Comment::where('post_id', $post->id)
                ->where('user_id', $user->id)
                ->whereNull('parent_id')
                ->count();

            if ($ownerCommentCount >= 2) {
                return response()->json([
                    'message' => 'Kamu hanya bisa membuat maksimal 2 komentar di postingan sendiri.',
                ], 422);
            }
        }

        // Validasi parent_id
        if (!empty($validated['parent_id'])) {
            $parent = Comment::find($validated['parent_id']);
            if ($parent->post_id !== $post->id) {
                return response()->json(['message' => 'Reply tidak valid.'], 422);
            }
            if (!is_null($parent->parent_id)) {
                return response()->json(['message' => 'Tidak bisa reply lebih dari 1 level.'], 422);
            }
        }

        $comment = Comment::create([
            'post_id'   => $post->id,
            'user_id'   => $user->id,
            'parent_id' => $validated['parent_id'] ?? null,
            'body'      => $validated['body'],
        ]);

        $postOwner = User::findOrFail($post->user_id);

        // Notif ke owner post kalau ada yang comment
        if (empty($validated['parent_id'])) {
            $this->notificationService->send(
                recipient    : $postOwner,
                actor        : $request->user(),
                type         : 'comment',
                referenceId  : $comment->id,
                referenceType: 'comment',
            );
        } else {
            // Notif ke owner post kalau ada reply di postnya
            $this->notificationService->send(
                recipient    : $postOwner,
                actor        : $request->user(),
                type         : 'reply_on_post',
                referenceId  : $comment->id,
                referenceType: 'comment',
            );

            // Notif ke owner comment yang di-reply
            $parentComment = Comment::findOrFail($validated['parent_id']);
            $commentOwner  = User::findOrFail($parentComment->user_id);

            $this->notificationService->send(
                recipient    : $commentOwner,
                actor        : $request->user(),
                type         : 'reply',
                referenceId  : $comment->id,
                referenceType: 'comment',
            );
        }

        $comment->load('user:id,username,avatar_url');

        return response()->json(['message' => 'Komentar berhasil dibuat.', 'data' => $comment], 201);
    }

    // Edit komentar — owner only
    public function update(Request $request, Comment $comment): JsonResponse
    {
        $user = $request->user();

        if ($user->id !== $comment->user_id) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        if (!$comment->post->isAccessible()) {
            return response()->json(['message' => 'Post sudah ditutup.'], 422);
        }

        $editCount = CommentEditHistory::where('comment_id', $comment->id)
                ->where('edited_by', $user->id)
                ->count();

        if ($editCount >= 2) {
            return response()->json(['message' => 'Komentar hanya bisa diedit maksimal 2 kali.'], 422);
        }

        $validated = $request->validate([
            'body' => 'required|string|min:5',
        ]);

        // Simpan history sebelum diupdate
        CommentEditHistory::create([
            'comment_id'  => $comment->id,
            'edited_by'   => $user->id,
            'body_before' => $comment->body,
            'body_after'  => $validated['body'],
            'edited_at'   => now(),
        ]);

        $comment->update($validated);

        return response()->json(['message' => 'Komentar berhasil diupdate.', 'data' => $comment]);
    }

    // Tambah method untuk lihat edit history comment (public)
    public function history(Request $request, Comment $comment): JsonResponse
    {
        if (!$request->user()->isModeratorOrAdmin()) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $history = CommentEditHistory::where('comment_id', $comment->id)
            ->with('editor:id,username,avatar_url')
            ->latest('edited_at')
            ->get();

        return response()->json(['data' => $history]);
    }

    public function destroy(Request $request, Comment $comment): JsonResponse
    {
        $user = $request->user();

        if (!$user->isModeratorOrAdmin()) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        // Cek post masih open
        if (!$comment->post->isAccessible()) {
            return response()->json(['message' => 'Post sudah ditutup.'], 422);
        }

        if ($comment->replies()->exists()) {
            $comment->update(['body' => '[komentar telah dihapus]']);
        } else {
            $comment->delete();
        }

        return response()->json(['message' => 'Komentar berhasil dihapus.']);
    }

    // Accept answer — hanya owner post
    public function accept(Request $request, Post $post, Comment $comment): JsonResponse
    {
        // Hanya owner post yang bisa accept
        if ($request->user()->id !== $post->user_id) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        if (!$post->isAccessible()) {
            return response()->json(['message' => 'Post sudah ditutup.'], 422);
        }

        // Tidak bisa accept reply, hanya top-level comment
        if (!is_null($comment->parent_id)) {
            return response()->json(['message' => 'Tidak bisa accept reply.'], 422);
        }

        // Unaccept comment sebelumnya jika ada
        if ($post->accepted_answer_id) {
            Comment::where('id', $post->accepted_answer_id)
                ->update(['is_accepted' => false]);
        }

        // Accept comment baru
        $comment->update(['is_accepted' => true]);
        $post->update([
            'accepted_answer_id' => $comment->id,
            'is_answered'        => true,
        ]);

        $answerer = User::findOrFail($comment->user_id);

        $this->pointService->adjust(
            $answerer,
            10,
            'answer_accepted',
            $comment->id,
            'Jawaban kamu diterima'
        );

        $this->notificationService->send(
            recipient    : $answerer,
            actor        : $request->user(),
            type         : 'answer_accepted',
            referenceId  : $comment->id,
            referenceType: 'comment',
        );

        return response()->json(['message' => 'Jawaban berhasil diterima.']);
    }

    // Unaccept answer
    public function unaccept(Request $request, Post $post): JsonResponse
    {
        if ($request->user()->id !== $post->user_id) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        if (!$post->isAccessible()) {
            return response()->json(['message' => 'Post sudah ditutup.'], 422);
        }

        Comment::where('id', $post->accepted_answer_id)
            ->update(['is_accepted' => false]);

        $post->update([
            'accepted_answer_id' => null,
            'is_answered'        => false,
        ]);

        return response()->json(['message' => 'Accepted answer berhasil dibatalkan.']);
    }
}
