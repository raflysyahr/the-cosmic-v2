<?php

namespace App\Modules\Discuss\Services;

use App\Modules\Discuss\Contracts\MessageServiceContract;
use App\Modules\Discuss\Data\SendMessageData;
use App\Modules\Discuss\Enums\MessageType;
use App\Modules\Discuss\Events\MessageDeleted;
use App\Modules\Discuss\Events\MessageEdited;
use App\Modules\Discuss\Events\MessageSent;
use App\Modules\Discuss\Models\Member;
use App\Modules\Discuss\Models\Message;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;

class MessageService implements MessageServiceContract
{
    public function send(SendMessageData $data): Message
    {
        $member = Member::where('room_id', $data->roomId)
            ->where('user_id', $data->userId)
            ->first();

        if (! $member || $member->is_banned) {
            throw ValidationException::withMessages([
                'message' => ['You are not allowed to send messages in this room.'],
            ]);
        }

        if ($member->muted_until && $member->muted_until->isFuture()) {
            throw ValidationException::withMessages([
                'message' => ['You are muted until ' . $member->muted_until->format('Y-m-d H:i')],
            ]);
        }

        $message = Message::create([
            'room_id' => $data->roomId,
            'user_id' => $data->userId,
            'reply_to_id' => $data->replyToId,
            'type' => $data->type,
            'body' => $data->body,
            'attachments' => $data->attachments,
            'metadata' => $data->metadata,
            'is_edited' => false,
            'is_deleted' => false,
        ]);

        event(new MessageSent($message));

        return $message;
    }

    /**
     * @param array{type: string, url: string, metadata?: array<string, mixed>}|null $attachment
     *        New attachment to put on the message (replaces the current one).
     */
    public function edit(Message $message, ?string $newBody, string $byUserId, ?array $attachment = null): Message
    {
        if ((string) $message->user_id !== (string) $byUserId) {
            throw ValidationException::withMessages([
                'message' => ['You are not allowed to edit this message.'],
            ]);
        }

        if (! in_array($message->type, [MessageType::Text, MessageType::Image, MessageType::File, MessageType::Video], true)) {
            throw ValidationException::withMessages([
                'message' => ['This message cannot be edited.'],
            ]);
        }

        $willHaveAttachment = $attachment !== null || ! empty($message->attachments);
        if (! $willHaveAttachment && trim((string) $newBody) === '') {
            throw ValidationException::withMessages([
                'body' => ['The body field is required.'],
            ]);
        }

        $attributes = [
            'body' => $newBody,
            'is_edited' => true,
        ];

        if ($attachment !== null) {
            // Drop the previous file/video metadata, keep anything unrelated.
            $metadata = $message->metadata ?? [];
            unset($metadata['file'], $metadata['video']);

            $attributes['type'] = $attachment['type'];
            $attributes['attachments'] = [$attachment['url']];
            $attributes['metadata'] = array_merge($metadata, $attachment['metadata'] ?? []);
        }

        $message->update($attributes);

        event(new MessageEdited($message));

        return $message;
    }

    public function delete(Message $message, string $byUserId): void
    {
        $isOwner = (string) $message->user_id === (string) $byUserId;

        if (! $isOwner) {
            $actor = Member::where('room_id', $message->room_id)
                ->where('user_id', $byUserId)
                ->first();

            $isModerator = $actor
                && ! $actor->is_banned
                && in_array($actor->role->value, ['moderator', 'admin'], true);

            if (! $isModerator) {
                throw ValidationException::withMessages([
                    'message' => ['You are not allowed to delete this message.'],
                ]);
            }
        }

        $message->update([
            'is_deleted' => true,
            'deleted_by_user_id' => $byUserId,
        ]);

        event(new MessageDeleted($message));
    }

    public function paginate(string $roomId, int $limit = 50): Collection
    {
        return Message::where('room_id', $roomId)
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }
}
