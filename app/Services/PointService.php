<?php

namespace App\Services;

use App\Models\PointsLog;
use App\Models\User;

class PointService
{
    public function __construct(private NotificationService $notificationService) {}

    private const LEVELS = [
        1  => 0,
        2  => 15,
        3  => 20,
        4  => 30,
        5  => 40,
        6  => 50,
        7  => 60,
        8  => 70,
        9  => 80,
        10 => 90,
    ];

    public function adjust(
        User $user,
        int $delta,
        string $actionType,
        ?string $referenceId = null,
        ?string $description = null
    ): void {
        if ($delta === 0) return;

        // Points tidak bisa kurang dari 1
        $newPoints    = max(1, $user->reputation_points + $delta);
        $actualDelta  = $newPoints - $user->reputation_points;

        if ($actualDelta === 0) return;

        $user->increment('reputation_points', $actualDelta);

        PointsLog::create([
            'user_id'      => $user->id,
            'points'       => $actualDelta,
            'action_type'  => $actionType,
            'reference_id' => $referenceId,
            'description'  => $description,
        ]);
        $this->updateLevel($user);
    }

    public function updateLevel(User $user): void
    {
        $user->refresh(); // ambil points terbaru

        $level = 1;
        foreach (self::LEVELS as $lvl => $minPoints) {
            if ($user->reputation_points >= $minPoints) {
                $level = $lvl;
            }
        }

        if ($user->level !== $level) {
            $user->update(['level' => $level]);
            $this->notificationService->send(
                        recipient    : $user,
                        actor        : null,
                        type         : 'level_up',
                        referenceId  : null,
                        referenceType: null,
                    );
        }
    }

    public function recalculate(User $user): void
    {
        $total = PointsLog::where('user_id', $user->id)->sum('points');
        // Minimum 1
        $final = max(1, $total);

        $user->update(['reputation_points' => $final]);

        $this->updateLevel($user);
    }
}
