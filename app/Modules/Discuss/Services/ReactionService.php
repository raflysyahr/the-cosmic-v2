<?php

namespace App\Modules\Discuss\Services;

use App\Modules\Discuss\Events\ReactionToggled;
use App\Modules\Discuss\Models\Message;
use App\Modules\Discuss\Models\Reaction;
use Illuminate\Validation\ValidationException;

class ReactionService
{
    public function toggle(string $roomId, string $messageId, string $userId, string $emoteId): void
    {
        $message = Message::where('id', $messageId)->where('room_id', $roomId)->first();
        if (!$message || $message->user_id === $userId) {
            throw ValidationException::withMessages(['message' => 'Cannot react to your own message.']);
        }

        $existing = Reaction::where('message_id', $messageId)
            ->where('user_id', $userId)
            ->first();

        if ($existing) {
            if ($existing->emote_id === $emoteId) {
                $existing->delete();
                event(new ReactionToggled($roomId, $messageId, $userId, $emoteId, 'removed'));
            } else {
                $oldEmoteId = $existing->emote_id;
                $existing->delete();
                event(new ReactionToggled($roomId, $messageId, $userId, $oldEmoteId, 'removed'));

                Reaction::create([
                    'message_id' => $messageId,
                    'user_id' => $userId,
                    'emote_id' => $emoteId,
                ]);
                event(new ReactionToggled($roomId, $messageId, $userId, $emoteId, 'added'));
            }
        } else {
            Reaction::create([
                'message_id' => $messageId,
                'user_id' => $userId,
                'emote_id' => $emoteId,
            ]);
            event(new ReactionToggled($roomId, $messageId, $userId, $emoteId, 'added'));
        }
    }
}
