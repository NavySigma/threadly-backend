<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Post;
use App\Models\Tag;
use App\Models\User;
use App\Services\SearchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SearchController extends Controller
{
    public function __construct(private SearchService $searchService) {}

    public function global(Request $request): JsonResponse
    {
        $request->validate([
            'q' => 'required|string|min:1|max:100', // ← min 1 huruf
        ]);

        $q = $request->q;
        $terms = $this->searchService->buildSearchTerms($q);

        $posts = $this->searchPosts($q, $terms)->limit(5)->get();
        $users = $this->searchUsers($q, $terms)->limit(5)->get();
        $tags = $this->searchTags($q, $terms)->limit(5)->get();

        $categories = Category::where('name', 'like', "%{$q}%")
            ->select('id', 'name', 'slug', 'description')
            ->limit(5)
            ->get();

        return response()->json([
            'query' => $q,
            'data' => compact('posts', 'users', 'tags', 'categories'),
        ]);
    }

    public function posts(Request $request): JsonResponse
    {
        $request->validate([
            'q' => 'nullable|string|min:1|max:100',
            'category_id' => 'nullable|uuid|exists:categories,id',
            'category_slug' => 'nullable|string|exists:categories,slug',
            'tag_id' => 'nullable|uuid|exists:tags,id',
            'tag_slug' => 'nullable|string|exists:tags,slug',
            'user_id' => 'nullable|uuid|exists:users,id',
            'is_answered' => 'nullable|boolean',
            'sort' => 'nullable|in:latest,oldest,popular,votes',
        ]);

        $q = $request->q;
        $terms = $q ? $this->searchService->buildSearchTerms($q) : null;

        $query = Post::where('status', 'open')
            ->when($q && $terms, fn ($query) => $this->applyPostSearch($query, $q, $terms))
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
            ->with(['user:id,username,avatar_url', 'category:id,name,slug', 'tags:id,name,slug,color']);

        return response()->json($query->paginate(15));
    }

    public function users(Request $request): JsonResponse
    {
        $request->validate([
            'q' => 'required|string|min:1|max:100',
        ]);

        $terms = $this->searchService->buildSearchTerms($request->q);

        $users = $this->searchUsers($request->q, $terms)
            ->withCount([
                'posts' => fn ($q) => $q->where('status', 'open'),
                'followers',
            ])
            ->paginate(15);

        return response()->json($users);
    }

    public function tags(Request $request): JsonResponse
    {
        $request->validate([
            'q' => 'required|string|min:1|max:100',
        ]);

        $terms = $this->searchService->buildSearchTerms($request->q);

        $tags = $this->searchTags($request->q, $terms)->paginate(20);

        return response()->json($tags);
    }

    // ─── Private helpers ────────────────────────────────────

    private function applyPostSearch($query, string $q, array $terms)
    {
        return $query->where(function ($q) use ($terms) {
            // 1. Fulltext search
            $q->whereRaw(
                'MATCH(title, body) AGAINST(? IN BOOLEAN MODE)',
                [$terms['fulltext']]
            )
            // 2. Fallback LIKE tiap kata
                ->orWhere(function ($q) use ($terms) {
                    foreach ($terms['words'] as $word) {
                        $q->orWhere('title', 'like', "%{$word}%")
                            ->orWhere('body', 'like', "%{$word}%");
                    }
                })
            // 3. Inisial kata — cari username yang huruf awalnya cocok
                ->orWhere(function ($q) use ($terms) {
                    foreach ($terms['initials'] as $initial) {
                        $q->orWhere('title', 'like', "{$initial}%");
                    }
                });
        });
    }

    private function searchPosts(string $q, array $terms)
    {
        return Post::where('status', 'open')
            ->where(function ($query) use ($q, $terms) {
                $this->applyPostSearch($query, $q, $terms);
            })
            ->with(['user:id,username,avatar_url', 'category:id,name,slug', 'tags:id,name,slug,color'])
            ->latest();
    }

    private function searchUsers(string $q, array $terms)
    {
        return User::select('id', 'username', 'avatar_url', 'reputation_points', 'level', 'created_at')
            ->where(function ($query) use ($q, $terms) {
                // 1. Fulltext
                $query->whereRaw(
                    'MATCH(username) AGAINST(? IN BOOLEAN MODE)',
                    [$terms['fulltext']]
                )
                // 2. LIKE
                    ->orWhere('username', 'like', "%{$q}%")
                // 3. Inisial — "mu" bisa match "malik udin"
                    ->orWhere(function ($q) use ($terms) {
                        foreach ($terms['initials'] as $initial) {
                            $q->orWhere('username', 'like', "{$initial}%");
                        }
                    });
            });
    }

    private function searchTags(string $q, array $terms)
    {
        return Tag::select('id', 'name', 'slug', 'color', 'usage_count')
            ->where(function ($query) use ($q, $terms) {
                // 1. Fulltext
                $query->whereRaw(
                    'MATCH(name) AGAINST(? IN BOOLEAN MODE)',
                    [$terms['fulltext']]
                )
                // 2. LIKE
                    ->orWhere('name', 'like', "%{$q}%")
                // 3. Starts with
                    ->orWhere('name', 'like', "{$q}%");
            })
            ->orderByDesc('usage_count');
    }
}
