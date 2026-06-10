<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Bookmark;
use App\Models\Post;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BookmarkController extends Controller
{
    // List semua bookmark post milik user
    public function index(Request $request): JsonResponse
    {
        $bookmarks = Bookmark::where('user_id', $request->user()->id)
            ->with(['post' => fn($q) => $q->with([
                'user:id,username,avatar_url',
                'category:id,name,slug',
                'tags:id,name,slug,color',
            ])])
            ->latest('created_at')
            ->paginate(15);

        return response()->json($bookmarks);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'post_id' => 'required|uuid|exists:posts,id',
        ]);

        $post = Post::findOrFail($validated['post_id']);

        // Post deleted tidak bisa di-bookmark
        if ($post->status === 'deleted') {
            return response()->json(['message' => 'Post tidak tersedia.'], 422);
        }

        $already = Bookmark::where('user_id', $request->user()->id)
            ->where('post_id', $validated['post_id'])
            ->exists();

        if ($already) {
            return response()->json(['message' => 'Sudah di-bookmark.'], 422);
        }

        Bookmark::create([
            'user_id'    => $request->user()->id,
            'post_id'    => $validated['post_id'],
            'created_at' => now(),
        ]);

        return response()->json(['message' => 'Post berhasil di-bookmark.'], 201);
    }

    public function destroy(Request $request, Post $post): JsonResponse
    {
        $deleted = Bookmark::where('user_id', $request->user()->id)
            ->where('post_id', $post->id)
            ->delete();

        if (!$deleted) {
            return response()->json(['message' => 'Bookmark tidak ditemukan.'], 404);
        }

        return response()->json(['message' => 'Bookmark berhasil dihapus.']);
    }

    // Cek apakah user sudah bookmark post tertentu
    public function check(Request $request, Post $post): JsonResponse
    {
        $isBookmarked = Bookmark::where('user_id', $request->user()->id)
            ->where('post_id', $post->id)
            ->exists();

        return response()->json(['is_bookmarked' => $isBookmarked]);
    }
}
