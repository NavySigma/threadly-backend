<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FollowController extends Controller
{
    public function __construct(private NotificationService $notificationService) {}

    public function follow(Request $request, User $user): JsonResponse
    {
        $me = $request->user();

        // Tidak bisa follow diri sendiri
        if ($me->id === $user->id) {
            return response()->json(['message' => 'Tidak bisa follow diri sendiri.'], 422);
        }

        // Cek sudah follow belum
        $alreadyFollowing = $me->following()->where('following_id', $user->id)->exists();
        if ($alreadyFollowing) {
            return response()->json(['message' => 'Sudah mengikuti user ini.'], 422);
        }

        $me->following()->attach($user->id, ['created_at' => now()]);

        $this->notificationService->send(
            recipient    : $user,
            actor        : $request->user(),
            type         : 'follow',
            referenceId  : $request->user()->id,
            referenceType: 'user',
        );

        return response()->json(['message' => 'Berhasil mengikuti.'], 201);
    }

    public function unfollow(Request $request, User $user): JsonResponse
    {
        $request->user()->following()->detach($user->id);

        return response()->json(['message' => 'Berhasil unfollow.']);
    }

    public function followers(User $user): JsonResponse
    {
        $user->loadCount('followers');

        $followers = $user->followers()
            ->select('users.id', 'username', 'avatar_url', 'reputation_points')
            ->get();

        return response()->json([
            'data' => $followers,
            'meta' => [
                'followers_count' => $user->followers_count,
            ],
        ]);
    }

    public function following(User $user): JsonResponse
    {
        $user->loadCount('following');

        $following = $user->following()
            ->select('users.id', 'username', 'avatar_url', 'reputation_points')
            ->get();

        return response()->json([
            'data' => $following,
            'meta' => [
                'following_count' => $user->following_count,
            ],
        ]);
    }
}
