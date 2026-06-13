<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Comment;
use App\Models\Like;
use App\Models\Post;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LikeController extends Controller
{
    public function __construct(private NotificationService $notificationService) {}

    public function like(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'target_type' => 'required|in:post,comment',
            'target_id'   => 'required|uuid',
        ]);

        // Pastikan target exists
        $target = match($validated['target_type']) {
            'post'    => Post::findOrFail($validated['target_id']),
            'comment' => Comment::findOrFail($validated['target_id']),
        };

            // Cek post accessible
        $post = $validated['target_type'] === 'post'
            ? $target
            : Post::findOrFail($target->post_id);

        if (!$post->isAccessible()) {
            return response()->json(['message' => 'Post sudah ditutup.'], 422);
        }

        $alreadyLiked = Like::where('user_id', $request->user()->id)
            ->where('target_id', $validated['target_id'])
            ->where('target_type', $validated['target_type'])
            ->exists();

        if ($alreadyLiked) {
            return response()->json(['message' => 'Sudah di-bookmark.'], 422);
        }

        Like::create([
            'user_id'     => $request->user()->id,
            'target_id'   => $validated['target_id'],
            'target_type' => $validated['target_type'],
        ]);

        $postOwner = User::findOrFail($post->user_id);

        $this->notificationService->send(
            recipient    : $postOwner,
            actor        : $request->user(),
            type         : 'like',
            referenceId  : $post->id,
            referenceType: 'post',
        );

        return response()->json(['message' => 'Berhasil di-bookmark.'], 201);
    }

    public function unlike(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'target_type' => 'required|in:post,comment',
            'target_id'   => 'required|uuid',
        ]);

        Like::where('user_id', $request->user()->id)
            ->where('target_id', $validated['target_id'])
            ->where('target_type', $validated['target_type'])
            ->delete();

        return response()->json(['message' => 'Bookmark dihapus.']);
    }

    // Semua bookmark post milik user yang login
    public function likedPosts(Request $request): JsonResponse
    {
        $posts = Like::where('user_id', $request->user()->id)
            ->where('target_type', 'post')
            ->with(['target' => fn($q) => $q->with([
                'user:id,username,avatar_url',
                'category:id,name,slug',
                'tags:id,name,slug,color',
            ])])
            ->latest('created_at')
            ->paginate(15);

        return response()->json($posts);
    }

    // Semua bookmark comment milik user yang login
    public function likedComments(Request $request): JsonResponse
    {
        $comments = Like::where('user_id', $request->user()->id)
            ->where('target_type', 'comment')
            ->with(['target' => fn($q) => $q->with([
                'user:id,username,avatar_url',
                'post:id,title',
            ])])
            ->latest('created_at')
            ->paginate(15);

        return response()->json($comments);
    }

    public function check(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'target_type' => 'required|in:post,comment',
            'target_id'   => 'required|uuid',
        ]);

        $isLiked = Like::where('user_id', $request->user()->id)
            ->where('target_id', $validated['target_id'])
            ->where('target_type', $validated['target_type'])
            ->exists();

        return response()->json(['is_liked' => $isLiked]);
    }
}
