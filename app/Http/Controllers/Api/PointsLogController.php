<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PointsLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Services\PointService;

class PointsLogController extends Controller
{
    // History points milik sendiri
    public function index(Request $request): JsonResponse
    {
        $logs = PointsLog::where('user_id', $request->user()->id)
            ->when($request->action_type, fn($q) =>
                $q->where('action_type', $request->action_type)
            )
            ->when($request->type, function($q) use ($request) {
                match($request->type) {
                    'earn'   => $q->where('points', '>', 0),
                    'deduct' => $q->where('points', '<', 0),
                    default  => null,
                };
            })
            ->latest('created_at')
            ->paginate(100);

        // Total summary
        $summary = [
            'current_points' => $request->user()->reputation_points,
            'total_earned'   => PointsLog::where('user_id', $request->user()->id)
                                    ->where('points', '>', 0)
                                    ->sum('points'),
            'total_deducted' => PointsLog::where('user_id', $request->user()->id)
                                    ->where('points', '<', 0)
                                    ->sum('points'),
        ];

        return response()->json([
            'summary' => $summary,
            'data'    => $logs,
        ]);
    }

    // Admin bisa lihat history points semua user
    public function userHistory(Request $request, string $userId): JsonResponse
    {
        if (!$request->user()->isAdmin()) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $user = \App\Models\User::findOrFail($userId);

        $logs = PointsLog::where('user_id', $userId)
            ->latest('created_at')
            ->paginate(100);

        $summary = [
            'current_points' => $user->reputation_points,
            'total_earned'   => PointsLog::where('user_id', $userId)
                                    ->where('points', '>', 0)
                                    ->sum('points'),
            'total_deducted' => PointsLog::where('user_id', $userId)
                                    ->where('points', '<', 0)
                                    ->sum('points'),
        ];

        return response()->json([
            'user'    => [
                'id'       => $user->id,
                'username' => $user->username,
                'avatar_url' => $user->avatar_url,
            ],
            'summary' => $summary,
            'data'    => $logs,
        ]);
    }

    public function __construct(private PointService $pointService) {}

    public function recalculate(Request $request, string $userId): JsonResponse
    {
        if (!$request->user()->isAdmin()) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $user = \App\Models\User::findOrFail($userId);
        $this->pointService->recalculate($user);

        return response()->json([
            'message'           => 'Points berhasil direcalculate.',
            'reputation_points' => $user->fresh()->reputation_points,
        ]);
    }
}
