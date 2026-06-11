<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CategoryController extends Controller
{
    // Public - semua bisa lihat
    public function index(): JsonResponse
    {
        $categories = cache()->remember('categories.all', now()->addHour(), function () {
            return Category::with('children')
                ->whereNull('parent_id')
                ->get();
        });

        return response()->json(['data' => $categories]);
    }

    public function show(Category $category): JsonResponse
    {
        $cacheKey = "categories.show.{$category->id}";

        $category = cache()->remember($cacheKey, now()->addHour(), function () use ($category) {
            $category->load(['children', 'posts' => fn ($q) => $q
                ->where('status', 'open')
                ->with(['user:id,username,avatar_url', 'tags:id,name,slug,color'])
                ->latest()
                ->limit(10),
            ]);

            return $category;
        });

        return response()->json(['data' => $category]);
    }

    // Admin only
    public function store(Request $request): JsonResponse
    {
        if (! $request->user()->isAdmin()) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'description' => 'nullable|string',
            'parent_id' => 'nullable|uuid|exists:categories,id',
        ]);

        $validated['slug'] = Str::slug($validated['name']);

        // Cek slug unique
        if (Category::where('slug', $validated['slug'])->exists()) {
            return response()->json(['message' => 'Category sudah ada.'], 422);
        }

        $category = Category::create($validated);

        $this->clearCategoryCache();
        return response()->json(['message' => 'Category berhasil dibuat.', 'data' => $category], 201);
    }

    public function update(Request $request, Category $category): JsonResponse
    {
        if (! $request->user()->isAdmin()) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $validated = $request->validate([
            'name' => 'sometimes|string|max:100',
            'description' => 'nullable|string',
            'parent_id' => 'nullable|uuid|exists:categories,id',
        ]);

        if (isset($validated['name'])) {
            $slug = Str::slug($validated['name']);

            if (Category::where('slug', $slug)->where('id', '!=', $category->id)->exists()) {
                return response()->json(['message' => 'Category sudah ada.'], 422);
            }

            $validated['slug'] = $slug;
        }

        // Tidak bisa set parent ke diri sendiri
        if (isset($validated['parent_id']) && $validated['parent_id'] === $category->id) {
            return response()->json(['message' => 'Tidak bisa set parent ke diri sendiri.'], 422);
        }

        $category->update($validated);

        $this->clearCategoryCache($category->id);
        return response()->json(['message' => 'Category berhasil diupdate.', 'data' => $category]);
    }

    public function destroy(Request $request, Category $category): JsonResponse
    {
        if (! $request->user()->isAdmin()) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        // Tidak bisa hapus kalau masih ada post
        if ($category->posts()->exists()) {
            return response()->json(['message' => 'Category masih memiliki post, tidak bisa dihapus.'], 422);
        }

        // Pindahkan children ke root
        $category->children()->update(['parent_id' => null]);

        $category->delete();

        $this->clearCategoryCache($category->id);
        return response()->json(['message' => 'Category berhasil dihapus.']);
    }

    private function clearCategoryCache(string $categoryId = null): void
    {
        cache()->forget('categories.all');
        if ($categoryId) {
            cache()->forget("categories.show.{$categoryId}");
        }
    }
}
