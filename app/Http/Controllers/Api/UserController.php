<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    // Lihat profile sendiri
    public function me(Request $request): JsonResponse
    {
        $user = $request->user()->load(['roles:id,name']);

        return response()->json([
            'data' => array_merge($user->toArray(), [
                'level_title'       => $user->level_title,
                'next_level_points' => $user->next_level_points,
            ])
        ]);
    }

    // Lihat profile orang lain (public)
    public function show(User $user): JsonResponse
    {
        $cacheKey = "users.show.{$user->id}";

        $data = cache()->remember($cacheKey, now()->addMinutes(10), function () use ($user) {
            $data = $user->only([
                'id', 'username', 'avatar_url', 'bio', 'reputation_points', 'level', 'created_at'
            ]);

            $data['followers_count'] = $user->followers()->count();
            $data['following_count'] = $user->following()->count();
            $data['posts_count']     = $user->posts()->where('status', 'open')->count();

            return $data;
        });

        return response()->json(['data' => $data]);
    }

    // Edit profile sendiri
    public function update(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'username'   => [
                'sometimes', 'string', 'min:3', 'max:100',
                Rule::unique('users', 'username')->ignore($user->id),
            ],
            'avatar_url' => 'sometimes|nullable|url|max:500',
            'bio'        => 'sometimes|nullable|string|max:500',
        ]);

        $user->update($validated);

        cache()->forget("users.show.{$request->user()->id}");
        return response()->json(['message' => 'Profile berhasil diupdate.', 'data' => $user]);
    }

    // Ganti password
    public function updatePassword(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'current_password' => 'required|string',
            'new_password'     => 'required|string|min:8|confirmed', // butuh new_password_confirmation
        ]);

        $user = $request->user();

        // Verifikasi password lama
        if (!Hash::check($validated['current_password'], $user->password_hash)) {
            return response()->json(['message' => 'Password lama tidak sesuai.'], 422);
        }

        $user->update([
            'password_hash' => Hash::make($validated['new_password']),
        ]);

        // Revoke semua token lama, paksa login ulang
        $user->tokens()->delete();

        return response()->json(['message' => 'Password berhasil diubah, silakan login ulang.']);
    }

    // Lihat posts milik user tertentu (public)
    public function posts(User $user): JsonResponse
    {
        $posts = $user->posts()
            ->where('status', 'open')
            ->with(['category:id,name,slug', 'tags:id,name,slug,color'])
            ->latest()
            ->paginate(15);

        return response()->json($posts);
    }
}
