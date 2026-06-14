<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Comment;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Storage;

class UserController extends Controller
{
    // Daftar semua user (public)
    public function index(Request $request): JsonResponse
    {
        $query = User::select(['id', 'username', 'avatar_url', 'bio', 'reputation_points', 'level', 'created_at']);

        if ($search = $request->input('search')) {
            $query->where('username', 'like', "%{$search}%");
        }

        $sort = $request->input('sort', 'reputation');
        $query->orderBy(match ($sort) {
            'newest' => 'created_at',
            'name' => 'username',
            default => 'reputation_points',
        }, $sort === 'name' ? 'asc' : 'desc');

        $users = $query->paginate(20);

        $roleIds = \DB::table('user_roles')
            ->whereIn('user_id', $users->pluck('id'))
            ->pluck('role_id', 'user_id');

        $roleNames = \DB::table('roles')
            ->whereIn('id', $roleIds->values())
            ->pluck('name', 'id');

        $users->through(function ($user) use ($roleIds, $roleNames) {
            $user->role_name = $roleNames->get($roleIds->get($user->id));
            return $user;
        });

        return response()->json($users);
    }

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

            $data['level_title']       = $user->level_title;
            $data['next_level_points'] = $user->next_level_points;
            $data['followers_count']   = $user->followers()->count();
            $data['following_count']   = $user->following()->count();
            $data['posts_count']       = $user->posts()->where('status', 'open')->count();

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
                'sometimes', 'string', 'min:3', 'max:100', 'alpha_dash',
                Rule::unique('users', 'username')->ignore($user->id),
            ],
            'avatar'     => 'sometimes|nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
            'avatar_url' => 'sometimes|nullable|string|max:2048',
            'bio'        => 'sometimes|nullable|string|max:500',
        ]);

        // Handle upload avatar file
        if ($request->hasFile('avatar')) {
            // Hapus avatar lama kalau bukan URL eksternal (Google/GitHub)
            if ($user->avatar_url && str_contains($user->avatar_url, 'storage/avatars')) {
                $oldPath = str_replace(asset('storage') . '/', '', $user->avatar_url);
                Storage::disk('public')->delete($oldPath);
            }

            // Simpan avatar baru
            $path = $request->file('avatar')->store('avatars', 'public');
            $validated['avatar_url'] = asset("storage/{$path}");
        }

        unset($validated['avatar']);

        $user->update($validated);

        cache()->forget("users.show.{$request->user()->id}");
        return response()->json(['message' => 'Profile berhasil diupdate.', 'data' => $user]);
    }

    // Ganti password
    public function updatePassword(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'new_password'     => 'required|string|min:8|confirmed', // butuh new_password_confirmation
        ]);

        $user = $request->user();

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
            ->with(['category:id,name,slug', 'tags:id,name,slug,color'])
            ->latest()
            ->paginate(15);

        return response()->json($posts);
    }

    // Lihat komentar milik user tertentu (public)
    public function comments(User $user): JsonResponse
    {
        $comments = Comment::where('user_id', $user->id)
            ->with('post:id,title')
            ->latest()
            ->paginate(20);

        return response()->json($comments);
    }
}
