<?php

namespace App\Modules\Discuss\Services;

use App\Modules\Discuss\Events\ReactionToggled;
use App\Modules\Discuss\Models\Member;
use App\Modules\Discuss\Models\Message;
use App\Modules\Discuss\Models\Reaction;
use Illuminate\Validation\ValidationException;

class ReactionService
{
    public function toggle(string $roomId, string $messageId, string $userId, string $emoteId): void
    {
        // Hanya anggota aktif room yang boleh bereaksi. Dicek paling awal dan dengan pesan yang
        // sama apa pun isi messageId-nya, supaya orang luar tidak bisa menebak pesan di room
        // privat/DM lewat perbedaan respons. Member yang di-ban juga tidak boleh bereaksi.
        $member = Member::where('room_id', $roomId)->where('user_id', $userId)->first();
        if (! $member || $member->is_banned) {
            throw ValidationException::withMessages([
                'message' => ['You must be a member of this room to react.'],
            ]);
        }

        // Pesan terhapus tidak boleh menerima reaksi baru (isinya sudah ditarik dari klien).
        $message = Message::where('id', $messageId)->where('room_id', $roomId)->first();
        if (! $message || $message->is_deleted) {
            throw ValidationException::withMessages(['message' => ['Message not found.']]);
        }

        if ((string) $message->user_id === (string) $userId) {
            throw ValidationException::withMessages(['message' => ['Cannot react to your own message.']]);
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
