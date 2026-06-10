<?php

namespace App\Services;

use App\Models\Notification;
use App\Models\User;

class NotificationService
{
    public function send(
        User $recipient,
        User $actor = null,
        string $type,
        ?string $referenceId = null,
        ?string $referenceType = null,
    ): void {
        // Tidak kirim notif ke diri sendiri
        if ($actor && $recipient->id === $actor->id) return;

        Notification::create([
            'user_id'        => $recipient->id,
            'actor_id'       => $actor?->id,
            'type'           => $type,
            'reference_id'   => $referenceId,
            'reference_type' => $referenceType,
            'is_read'        => false,
            'created_at'     => now(),
        ]);
    }
}
