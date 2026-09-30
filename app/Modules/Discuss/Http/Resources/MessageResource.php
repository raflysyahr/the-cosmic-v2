<?php

namespace App\Modules\Discuss\Http\Resources;

use App\Modules\Auth\Models\User;
use App\Modules\Cultivation\Services\CultivationService;
use App\Modules\Discuss\Models\Emote;
use App\Modules\Discuss\Models\Message;
use App\Modules\Discuss\Models\Reaction;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MessageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $user = User::select('id', 'display_name', 'avatar_url')
            ->where('id', $this->user_id)
            ->first();

        // Badge realm cultivation — query manual lewat CultivationService
        // (modul terpisah, tidak ada relasi Eloquent lintas modul per
        // AGENTS.md §2). Dipanggil per-message (bukan batch di
        // controller) karena resource ini juga dipakai di alur broadcast
        // (MessageSent event) yang tidak lewat controller/collection.
        $realmBadge = app(CultivationService::class)->getRealmBadgeData([$this->user_id])[$this->user_id] ?? null;

        // Group reactions per emote, including the emote's code/url (resolved
        // via a batched cross-table query, no Eloquent relation per AGENTS.md)
        // and the list of reacting user_ids so the frontend can mark "reacted".
        // Produces GroupedReaction[] ({emoteId, emoteCode, imageUrl, count,
        // userIds}) — NOT a {emoteId: count} map, which the frontend can't read.
        $reactionRows = Reaction::where('message_id', $this->id)
            ->select('emote_id', 'user_id')
            ->get();

        $emoteIds = $reactionRows->pluck('emote_id')->filter()->unique()->values();
        $emotes = $emoteIds->isEmpty()
            ? collect()
            : Emote::select('id', 'code', 'image_url', 'unicode')
                ->whereIn('id', $emoteIds)
                ->get()
                ->keyBy('id');

        $reactions = $reactionRows
            ->groupBy('emote_id')
            ->map(function ($rows, $emoteId) use ($emotes) {
                $emote = $emotes->get($emoteId);

                return [
                    'emoteId'   => $emoteId,
                    'emoteCode' => $emote?->code,
                    'imageUrl'  => $emote?->image_url,
                    'unicode'   => $emote?->unicode,
                    'count'     => $rows->count(),
                    'userIds'   => $rows->pluck('user_id')->values()->all(),
                ];
            })
            ->values()
            ->all();

        $replyTo = null;
        if ($this->reply_to_id) {
            $replyToMsg = Message::select('id', 'user_id', 'body', 'type', 'attachments', 'metadata')
                ->where('id', $this->reply_to_id)
                ->first();
            if ($replyToMsg) {
                $replyToUser = User::select('id', 'display_name', 'avatar_url')
                    ->where('id', $replyToMsg->user_id)
                    ->first();
                $replyTo = [
                    'id' => $replyToMsg->id,
                    'body' => $replyToMsg->body,
                    'type' => $replyToMsg->type->value,
                    // Small preview image for photo/video replies (video: its poster frame).
                    'thumbnail' => match ($replyToMsg->type->value) {
                        'image' => $replyToMsg->metadata['thumbnail'] ?? $replyToMsg->attachments[0] ?? null,
                        'video' => $replyToMsg->metadata['video']['thumbnail'] ?? null,
                        default => null,
                    },
                    'file_name' => $replyToMsg->type->value === 'file' ? ($replyToMsg->metadata['file']['name'] ?? null) : null,
                    'user' => $replyToUser ? [
                        'id' => $replyToUser->id,
                        'display_name' => $replyToUser->display_name,
                        'avatar_url' => $replyToUser->avatar_url,
                    ] : ['display_name' => 'Unknown'],
                ];
            }
        }

        return [
            'id' => $this->id,
            'body' => $this->body,
            'type' => $this->type->value,
            'user' => $user ? [
                'id' => $user->id,
                'display_name' => $user->display_name,
                'avatar_url' => $user->avatar_url,
                'realm' => $realmBadge,
            ] : null,
            'attachments' => $this->attachments,
            'reply_to' => $replyTo,
            'reply_to_id' => $this->reply_to_id,
            'reply_count' => Message::where('reply_to_id', $this->id)
    ->distinct('user_id')
    ->count('user_id'),
            'reactions' => $reactions,
            'metadata' => $this->metadata,
            'is_edited' => $this->is_edited,
            'is_deleted' => $this->is_deleted,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
