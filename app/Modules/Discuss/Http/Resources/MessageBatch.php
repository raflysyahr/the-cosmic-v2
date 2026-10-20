<?php

namespace App\Modules\Discuss\Http\Resources;

use App\Modules\Auth\Models\User;
use App\Modules\Cultivation\Services\CultivationService;
use App\Modules\Discuss\Models\Emote;
use App\Modules\Discuss\Models\Message;
use App\Modules\Discuss\Models\MessageMark;
use App\Modules\Discuss\Models\Reaction;
use Illuminate\Support\Collection;

/**
 * Data pendukung untuk banyak MessageResource sekaligus, diambil dengan satu query per jenis
 * data (bukan satu query per pesan). Satu halaman 50 pesan sebelumnya memicu ratusan query
 * (pengirim, badge realm, reaksi, emote, mark, pesan yang dibalas, jumlah balasan); sekarang
 * jumlahnya tetap sekitar 8 berapa pun banyaknya pesan.
 *
 * Tidak ada relasi Eloquent lintas modul (aturan AGENTS.md §2), jadi semuanya query manual
 * seperti di MessageResource sebelumnya, hanya digabung per batch.
 */
final class MessageBatch
{
    /**
     * @param  Collection<string, User>  $users  keyed by id (pengirim pesan dan pengirim pesan yang dibalas)
     * @param  array<string, mixed>  $realmBadges  userId => data badge
     * @param  Collection<string, Collection>  $reactions  per message_id
     * @param  Collection<string, Emote>  $emotes  keyed by id
     * @param  Collection<string, Collection>  $marks  per message_id
     * @param  Collection<string, Message>  $replyParents  keyed by id
     * @param  Collection<string, int>  $replyCounts  reply_to_id => jumlah pembalas unik
     */
    private function __construct(
        public readonly Collection $users,
        public readonly array $realmBadges,
        public readonly Collection $reactions,
        public readonly Collection $emotes,
        public readonly Collection $marks,
        public readonly Collection $replyParents,
        public readonly Collection $replyCounts,
    ) {}

    /**
     * @param  iterable<int, Message>  $messages
     */
    public static function load(iterable $messages): self
    {
        $messages = collect($messages)->values();
        $messageIds = $messages->pluck('id')->filter()->unique()->values()->all();

        // Pesan yang dibalas (hanya yang satu room; kutipan lintas room tidak pernah dipakai).
        $replyIds = $messages->pluck('reply_to_id')->filter()->unique()->values()->all();
        $replyParents = $replyIds === []
            ? collect()
            : Message::select('id', 'room_id', 'user_id', 'body', 'type', 'attachments', 'metadata', 'is_deleted')
                ->whereIn('id', $replyIds)
                ->get()
                ->keyBy('id');

        $userIds = $messages->pluck('user_id')
            ->merge($replyParents->pluck('user_id'))
            ->filter()
            ->unique()
            ->values()
            ->all();

        $users = $userIds === []
            ? collect()
            : User::select('id', 'display_name', 'avatar_url')->whereIn('id', $userIds)->get()->keyBy('id');

        // Badge realm hanya untuk pengirim pesan; satu panggilan untuk semua pengirim.
        $senderIds = $messages->pluck('user_id')->filter()->unique()->values()->all();
        $realmBadges = $senderIds === []
            ? []
            : app(CultivationService::class)->getRealmBadgeData($senderIds);

        $reactions = $messageIds === []
            ? collect()
            : Reaction::whereIn('message_id', $messageIds)
                ->select('message_id', 'emote_id', 'user_id')
                ->get()
                ->groupBy('message_id');

        $emoteIds = $reactions->flatten(1)->pluck('emote_id')->filter()->unique()->values()->all();
        $emotes = $emoteIds === []
            ? collect()
            : Emote::select('id', 'code', 'image_url', 'unicode')->whereIn('id', $emoteIds)->get()->keyBy('id');

        $marks = $messageIds === []
            ? collect()
            : MessageMark::whereIn('message_id', $messageIds)
                ->select('message_id', 'kind', 'marked_by')
                ->get()
                ->groupBy('message_id');

        // Jumlah pembalas unik per pesan (semua balasan dihitung, termasuk yang sudah dihapus,
        // sama dengan perilaku sebelumnya).
        $replyCounts = $messageIds === []
            ? collect()
            : Message::whereIn('reply_to_id', $messageIds)
                ->selectRaw('reply_to_id, COUNT(DISTINCT user_id) as replier_count')
                ->groupBy('reply_to_id')
                ->pluck('replier_count', 'reply_to_id');

        return new self($users, $realmBadges, $reactions, $emotes, $marks, $replyParents, $replyCounts);
    }
}
