<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Tag;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class TagController extends Controller
{
    // Public
    public function index(Request $request): JsonResponse
    {
        $tags = Tag::when(
                $request->search,
                fn($q) => $q->where('name', 'like', "%{$request->search}%")
            )
            ->orderByDesc('usage_count')
            ->paginate(20);

        return response()->json($tags);
    }

    public function show(Tag $tag): JsonResponse
    {
        $tag->load(['posts' => fn($q) => $q
            ->where('status', 'open')
            ->with(['user:id,username,avatar_url', 'category:id,name,slug'])
            ->latest()
            ->limit(10)
        ]);

        return response()->json(['data' => $tag]);
    }

    // Admin only
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'  => 'required|string|max:50',
            'color' => 'nullable|string|regex:/^#[0-9A-Fa-f]{6}$/',
        ]);

        $slug = Str::slug($validated['name']);

        if (Tag::where('slug', $slug)->exists()) {
            return response()->json(['message' => 'Tag sudah ada.'], 422);
        }

        $tag = Tag::create([
            'name'  => $validated['name'],
            'slug'  => $slug,
            'color' => $validated['color'] ?? null,
        ]);

        return response()->json(['message' => 'Tag berhasil dibuat.', 'data' => $tag], 201);
    }

    public function update(Request $request, Tag $tag): JsonResponse
    {
        if (!$request->user()->isModeratorOrAdmin()) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $validated = $request->validate([
            'name'  => 'sometimes|string|max:50',
            'color' => 'nullable|string|regex:/^#[0-9A-Fa-f]{6}$/',
        ]);

        if (isset($validated['name'])) {
            $slug = Str::slug($validated['name']);

            if (Tag::where('slug', $slug)->where('id', '!=', $tag->id)->exists()) {
                return response()->json(['message' => 'Tag sudah ada.'], 422);
            }

            $validated['slug'] = $slug;
        }

        $tag->update($validated);

        return response()->json(['message' => 'Tag berhasil diupdate.', 'data' => $tag]);
    }

    public function destroy(Request $request, Tag $tag): JsonResponse
    {
        if (!$request->user()->isModeratorOrAdmin()) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        // Decrement usage_count sudah tidak relevan, langsung detach
        $tag->posts()->detach();
        $tag->delete();

        return response()->json(['message' => 'Tag berhasil dihapus.']);
    }
}
