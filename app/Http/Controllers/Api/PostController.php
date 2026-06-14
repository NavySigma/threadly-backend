<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Post;
use App\Models\Comment;
use App\Models\PostEditHistory;
use App\Models\Tag;
use App\Models\User;
use App\Models\Bookmark;
use App\Models\Vote;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PostController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $posts = Post::with([
            'user:id,username,avatar_url',
            'category:id,name,slug',
            'tags:id,name,slug,color',
        ])
            ->withCount('comments')
            ->where('status', 'open')
            ->when($request->search, fn ($q) => $q->where('title', 'like', "%{$request->search}%")
                ->orWhere('body', 'like', "%{$request->search}%")
            )
            ->when($request->category_id, fn ($q) => $q->where('category_id', $request->category_id)
            )
            ->when($request->category_slug, fn ($q) => $q->whereHas('category', fn ($q) => $q->where('slug', $request->category_slug)
            )
            )
            ->when($request->tag_id, fn ($q) => $q->whereHas('tags', fn ($q) => $q->where('tags.id', $request->tag_id)
            )
            )
            ->when($request->tag_slug, fn ($q) => $q->whereHas('tags', fn ($q) => $q->where('tags.slug', $request->tag_slug)
            )
            )
            ->when($request->user_id, fn ($q) => $q->where('user_id', $request->user_id)
            )
            ->when($request->is_answered !== null, fn ($q) => $q->where('is_answered', filter_var($request->is_answered, FILTER_VALIDATE_BOOLEAN))
            )
            ->when($request->sort, function ($q) use ($request) {
                match ($request->sort) {
                    'latest' => $q->latest(),
                    'oldest' => $q->oldest(),
                    'popular' => $q->orderByDesc('view_count'),
                    'votes' => $q->orderByDesc('vote_score'),
                    default => $q->latest(),
                };
            }, fn ($q) => $q->latest())
            ->paginate(15);

        return response()->json($posts->toArray());
    }

    public function store(Request $request): JsonResponse
    {
        if ($request->user()->is_banned) {
            return response()->json(['message' => 'Akun kamu telah diblokir.'], 403);
        }
        if ($request->user()->reputation_points < 15) {
            return response()->json(['message' => 'Minimal 15 poin untuk membuat postingan.'], 422);
        }
        $validated = $request->validate([
            'category_id' => 'required|uuid|exists:categories,id',
            'title' => 'required|string|max:300',
            'body' => 'required|string|max:50000',
            'tags' => 'nullable|array|max:10',
            'tags.*' => 'uuid|exists:tags,id',
        ]);

        $post = Post::create([
            'user_id' => $request->user()->id,
            'category_id' => $validated['category_id'],
            'title' => strip_tags($validated['title']),
            'body' => strip_tags($validated['body']),
        ]);

        // Attach tags jika ada
        if (! empty($validated['tags'])) {
            $post->tags()->attach($validated['tags']);

            // Update usage_count di tabel tags
            Tag::whereIn('id', $validated['tags'])->increment('usage_count');
        }

        $post->load(['user:id,username,avatar_url', 'category:id,name,slug', 'tags:id,name,slug,color']);

        return response()->json(['message' => 'Post berhasil dibuat.', 'data' => $post], 201);
    }

    public function show(Request $request, Post $post): JsonResponse
    {
        $post->increment('view_count');
        $post->load([
            'user:id,username,avatar_url,reputation_points',
            'category:id,name,slug',
            'tags:id,name,slug,color',
            'acceptedAnswer.user:id,username,avatar_url',
        ]);

        $postData = $post->toArray();
        $postData['likes_count'] = $post->likes()->count();

        $currentUser = $request->user('sanctum');
        if ($currentUser) {
            $postData['is_liked'] = $post->likes()
                ->where('user_id', $currentUser->id)
                ->exists();

            $postData['is_bookmarked'] = Bookmark::where('user_id', $currentUser->id)
                ->where('post_id', $post->id)
                ->exists();

            $postData['user_vote'] = Vote::where('user_id', $currentUser->id)
                ->where('target_id', $post->id)
                ->where('target_type', 'post')
                ->value('vote_type');
        }

        return response()->json(['data' => $postData]);
    }

    public function update(Request $request, Post $post): JsonResponse
    {
        $user = $request->user();

        if ($user->id !== $post->user_id) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        if ($post->status === 'closed') {
            return response()->json(['message' => 'Post sudah ditutup, tidak bisa diedit.'], 422);
        }

        $editCount = PostEditHistory::where('post_id', $post->id)
            ->where('edited_by', $user->id)
            ->count();

        if ($editCount >= 2) {
            return response()->json(['message' => 'Post hanya bisa diedit maksimal 2 kali.'], 422);
        }

        $validated = $request->validate([
            'category_id' => 'sometimes|uuid|exists:categories,id',
            'title' => 'sometimes|string|min:10|max:300',
            'body' => 'sometimes|string|min:20',
            'tags' => 'nullable|array|max:10',
            'tags.*' => 'uuid|exists:tags,id',
            'reason' => 'nullable|string|max:255',
        ]);

        // Simpan history sebelum diupdate
        if (isset($validated['body'])) {
            PostEditHistory::create([
                'post_id' => $post->id,
                'edited_by' => $user->id,
                'body_before' => $post->body,
                'body_after' => $validated['body'],
                'reason' => $validated['reason'] ?? null,
                'edited_at' => now(),
            ]);
        }

        $post->update(collect($validated)->except(['tags', 'reason'])->toArray());

        if ($request->has('tags')) {
            $oldTagIds = $post->tags()->pluck('tags.id')->toArray();
            if ($oldTagIds) {
                Tag::whereIn('id', $oldTagIds)->decrement('usage_count');
            }

            $newTags = $validated['tags'] ?? [];
            $post->tags()->sync($newTags);
            if ($newTags) {
                Tag::whereIn('id', $newTags)->increment('usage_count');
            }
        }

        $post->load(['user:id,username,avatar_url', 'category:id,name,slug', 'tags:id,name,slug,color']);

        return response()->json(['message' => 'Post berhasil diupdate.', 'data' => $post]);
    }

    // Tambah method untuk lihat edit history post (admin only)
    public function history(Request $request, Post $post): JsonResponse
    {
        if (! $request->user()->isModeratorOrAdmin()) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $history = PostEditHistory::where('post_id', $post->id)
            ->with('editor:id,username,avatar_url')
            ->latest('edited_at')
            ->get();

        return response()->json(['data' => $history]);
    }

    public function destroy(Request $request, Post $post): JsonResponse
    {
        $user = $request->user();

        // Owner ATAU mod ATAU admin bisa hapus
        if ($user->id !== $post->user_id && ! $user->isModeratorOrAdmin()) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $post->update(['status' => 'deleted']);

        return response()->json(['message' => 'Post berhasil dihapus.']);
    }

    // PostController

    public function close(Request $request, Post $post): JsonResponse
    {
        if ($request->user()->id !== $post->user_id) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        if ($post->status === 'deleted') {
            return response()->json(['message' => 'Post sudah dihapus.'], 422);
        }

        if ($post->status === 'closed') {
            return response()->json(['message' => 'Post sudah ditutup.'], 422);
        }

        $post->update([
            'status' => 'closed',
            'closed_at' => now(),
        ]);

        return response()->json([
            'message' => 'Post berhasil ditutup. Kamu punya waktu 24 jam untuk reopen.',
            'closed_at' => $post->closed_at,
            'reopen_deadline' => $post->closed_at->addHours(24),
        ]);
    }

    public function reopen(Request $request, Post $post): JsonResponse
    {
        if ($request->user()->id !== $post->user_id) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        if ($post->status !== 'closed') {
            return response()->json(['message' => 'Post tidak dalam kondisi closed.'], 422);
        }

        // Permanent closed — closed_at null artinya sudah lewat 24 jam (di-set scheduler)
        if (is_null($post->closed_at)) {
            return response()->json(['message' => 'Post sudah permanent closed, tidak bisa dibuka kembali.'], 422);
        }

        // Cek 24 jam
        if ($post->closed_at->diffInHours(now()) >= 24) {
            return response()->json(['message' => 'Post sudah permanent closed, tidak bisa dibuka kembali.'], 422);
        }

        $post->update([
            'status' => 'open',
            'closed_at' => null,
        ]);

        return response()->json(['message' => 'Post berhasil dibuka kembali.']);
    }

}
