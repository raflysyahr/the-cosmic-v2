<?php

namespace App\Modules\Discuss\Services;

use App\Modules\Discuss\Models\Notification;

class NotificationService
{
    public function create(string $userId, string $type, array $payload, ?string $actorId = null): Notification
    {
        return Notification::create([
            'user_id' => $userId,
            'type' => $type,
            'actor_user_id' => $actorId,
            'payload' => $payload,
            'is_read' => false,
        ]);
    }

    public function markAllRead(string $userId): void
    {
        Notification::where('user_id', $userId)
            ->where('is_read', false)
            ->update(['is_read' => true]);
    }

    public function unreadCount(string $userId): int
    {
        return Notification::where('user_id', $userId)
            ->where('is_read', false)
            ->count();
    }
}
