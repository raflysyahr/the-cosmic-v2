<?php

namespace App\Modules\Discuss\Listeners;

use App\Modules\Discuss\Events\MessageSent;
use App\Modules\Discuss\Models\Message;
use App\Modules\Discuss\Services\NotificationService;

class SendReplyNotification
{
    public function __construct(
        private readonly NotificationService $notificationService,
    ) {}

    public function handle(MessageSent $event): void
    {
        if (! $event->message->reply_to_id) {
            return;
        }

        $parentMessage = Message::find($event->message->reply_to_id);

        if (! $parentMessage || (string) $parentMessage->user_id === (string) $event->message->user_id) {
            return;
        }

        $this->notificationService->create(
            userId: $parentMessage->user_id,
            type: 'reply',
            payload: [
                'room_id' => $event->message->room_id,
                'message_id' => $event->message->id,
                'parent_message_id' => $event->message->reply_to_id,
            ],
            actorId: $event->message->user_id,
        );
    }
}
