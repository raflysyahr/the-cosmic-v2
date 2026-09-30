<?php

namespace App\Modules\Discuss\Listeners;

use App\Modules\Discuss\Events\MessageSent;
use App\Modules\Discuss\Services\NotificationService;

class SendMentionNotification
{
    public function __construct(
        private readonly NotificationService $notificationService,
    ) {}

    public function handle(MessageSent $event): void
    {
        $mentions = $event->message->metadata['mentions'] ?? [];

        if (empty($mentions)) {
            return;
        }

        foreach ($mentions as $mentionedUserId) {
            $this->notificationService->create(
                userId: $mentionedUserId,
                type: 'mention',
                payload: [
                    'room_id' => $event->message->room_id,
                    'message_id' => $event->message->id,
                ],
                actorId: $event->message->user_id,
            );
        }
    }
}
