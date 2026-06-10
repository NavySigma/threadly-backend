<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Post;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SearchController extends Controller
{
    // Global search semua sekaligus
    public function global(Request $request): JsonResponse
    {
        $request->validate([
            'q' => 'required|string|min:2|max:100',
        ]);

        $q = $request->q;

        $posts = Post::where('status', 'open')
            ->where(fn($query) => $query
                ->where('title', 'like', "%{$q}%")
                ->orWhere('body', 'like', "%{$q}%")
            )
            ->with(['user:id,username,avatar_url', 'category:id,name,slug', 'tags:id,name,slug,color'])
            ->latest()
            ->limit(5)
            ->get();

        $users = User::where('username', 'like', "%{$q}%")
            ->select('id', 'username', 'avatar_url', 'reputation_points', 'level')
            ->limit(5)
            ->get();

        $tags = Tag::where('name', 'like', "%{$q}%")
            ->select('id', 'name', 'slug', 'color', 'usage_count')
            ->orderByDesc('usage_count')
            ->limit(5)
            ->get();

        $categories = Category::where('name', 'like', "%{$q}%")
            ->select('id', 'name', 'slug', 'description')
            ->limit(5)
            ->get();

        return response()->json([
            'query' => $q,
            'data'  => [
                'posts'      => $posts,
                'users'      => $users,
                'tags'       => $tags,
                'categories' => $categories,
            ],
        ]);
    }

    // Search & filter post
    public function posts(Request $request): JsonResponse
    {
        $request->validate([
            'q'             => 'nullable|string|min:1|max:100',
            'category_id'   => 'nullable|uuid|exists:categories,id',
            'category_slug' => 'nullable|string|exists:categories,slug',
            'tag_id'        => 'nullable|uuid|exists:tags,id',
            'tag_slug'      => 'nullable|string|exists:tags,slug',
            'user_id'       => 'nullable|uuid|exists:users,id',
            'is_answered'   => 'nullable|boolean',
            'sort'          => 'nullable|in:latest,oldest,popular,votes',
        ]);

        $posts = Post::where('status', 'open')
            ->when($request->q, fn($query) =>
                $query->where('title', 'like', "%{$request->q}%")
                      ->orWhere('body', 'like', "%{$request->q}%")
            )
            ->when($request->category_id, fn($query) =>
                $query->where('category_id', $request->category_id)
            )
            ->when($request->category_slug, fn($query) =>
                $query->whereHas('category', fn($q) =>
                    $q->where('slug', $request->category_slug)
                )
            )
            ->when($request->tag_id, fn($query) =>
                $query->whereHas('tags', fn($q) =>
                    $q->where('tags.id', $request->tag_id)
                )
            )
            ->when($request->tag_slug, fn($query) =>
                $query->whereHas('tags', fn($q) =>
                    $q->where('tags.slug', $request->tag_slug)
                )
            )
            ->when($request->user_id, fn($query) =>
                $query->where('user_id', $request->user_id)
            )
            ->when($request->is_answered !== null, fn($query) =>
                $query->where('is_answered', filter_var($request->is_answered, FILTER_VALIDATE_BOOLEAN))
            )
            ->when($request->sort, function($query) use ($request) {
                match($request->sort) {
                    'latest'  => $query->latest(),
                    'oldest'  => $query->oldest(),
                    'popular' => $query->orderByDesc('view_count'),
                    'votes'   => $query->orderByDesc('vote_score'),
                    default   => $query->latest(),
                };
            }, fn($query) => $query->latest())
            ->with(['user:id,username,avatar_url', 'category:id,name,slug', 'tags:id,name,slug,color'])
            ->paginate(15);

        return response()->json($posts);
    }

    // Search user
    public function users(Request $request): JsonResponse
    {
        $request->validate([
            'q' => 'required|string|min:2|max:100',
        ]);

        $users = User::where('username', 'like', "%{$request->q}%")
            ->select('id', 'username', 'avatar_url', 'reputation_points', 'level', 'created_at')
            ->withCount([
                'posts'     => fn($q) => $q->where('status', 'open'),
                'followers',
            ])
            ->paginate(15);

        return response()->json($users);
    }

    // Search tag
    public function tags(Request $request): JsonResponse
    {
        $request->validate([
            'q' => 'required|string|min:2|max:100',
        ]);

        $tags = Tag::where('name', 'like', "%{$request->q}%")
            ->select('id', 'name', 'slug', 'color', 'usage_count')
            ->orderByDesc('usage_count')
            ->paginate(20);

        return response()->json($tags);
    }
}
