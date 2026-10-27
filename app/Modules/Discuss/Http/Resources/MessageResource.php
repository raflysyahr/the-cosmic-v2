<?php

namespace App\Modules\Discuss\Http\Resources;

use App\Modules\Discuss\Enums\MarkKind;
use App\Modules\Discuss\Services\MessageEntityService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MessageResource extends JsonResource
{
    /**
     * Data pendukung hasil batch. Diisi oleh collection() untuk seluruh halaman sekaligus;
     * kalau kosong (mis. satu pesan lewat broadcast/store), dimuat untuk pesan itu saja.
     */
    public ?MessageBatch $batch = null;

    /**
     * Muat data pendukung SEKALI untuk semua pesan di koleksi/paginator, supaya jumlah
     * query tidak bertambah per pesan (N+1).
     */
    public static function collection($resource)
    {
        $collection = parent::collection($resource);

        $resources = $collection->collection;
        $batch = MessageBatch::load($resources->map(fn ($r) => $r->resource));
        $resources->each(function ($r) use ($batch) {
            $r->batch = $batch;
        });

        return $collection;
    }

    public function toArray(Request $request): array
    {
        $batch = $this->batch ?? MessageBatch::load([$this->resource]);

        $user = $batch->users->get($this->user_id);

        // Badge realm cultivation (modul terpisah, tanpa relasi Eloquent lintas modul per
        // AGENTS.md §2); sudah dimuat batch untuk semua pengirim.
        $realmBadge = $batch->realmBadges[$this->user_id] ?? null;

        // Group reactions per emote, including the emote's code/url and the list of reacting
        // user_ids so the frontend can mark "reacted".
        // Produces GroupedReaction[] ({emoteId, emoteCode, imageUrl, count, userIds}) — NOT a
        // {emoteId: count} map, which the frontend can't read.
        $reactions = ($batch->reactions->get($this->id) ?? collect())
            ->groupBy('emote_id')
            ->map(function ($rows, $emoteId) use ($batch) {
                $emote = $batch->emotes->get($emoteId);

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

        // Helpful / Best Answer.
        $markRows = $batch->marks->get($this->id) ?? collect();
        $helpfulUserIds = $markRows
            ->filter(fn ($row) => $row->kind === MarkKind::Helpful)
            ->pluck('marked_by')
            ->values()
            ->all();

        $replyTo = null;
        if ($this->reply_to_id) {
            $replyToMsg = $batch->replyParents->get($this->reply_to_id);

            // Jangan pernah mengutip pesan dari room lain, walau data lama sudah terlanjur salah.
            if ($replyToMsg && $replyToMsg->room_id === $this->room_id) {
                $replyToUser = $batch->users->get($replyToMsg->user_id);
                // Pesan yang dikutip sudah dihapus: kirim kerangka kosong saja. Isi (teks,
                // thumbnail, nama file, entity) tidak boleh ikut keluar lewat kutipan.
                $parentDeleted = (bool) $replyToMsg->is_deleted;
                $replyTo = [
                    'id' => $replyToMsg->id,
                    'body' => $parentDeleted ? null : $replyToMsg->body,
                    'type' => $parentDeleted ? 'text' : $replyToMsg->type->value,
                    'is_deleted' => $parentDeleted,
                    // Small preview image for photo/video replies (video: its poster frame).
                    'thumbnail' => $parentDeleted ? null : match ($replyToMsg->type->value) {
                        'image' => $replyToMsg->metadata['thumbnail'] ?? $replyToMsg->attachments[0] ?? null,
                        'video' => $replyToMsg->metadata['video']['thumbnail'] ?? null,
                        default => null,
                    },
                    'file_name' => ! $parentDeleted && $replyToMsg->type->value === 'file' ? ($replyToMsg->metadata['file']['name'] ?? null) : null,
                    // Kutipan hanya menampilkan spoiler; media spoiler → thumbnail di-blur.
                    'entities' => $parentDeleted ? [] : app(MessageEntityService::class)->spoilersOnly($replyToMsg->metadata['entities'] ?? null),
                    'spoiler' => ! $parentDeleted && (bool) ($replyToMsg->metadata['spoiler'] ?? false),
                    'user' => $replyToUser ? [
                        'id' => $replyToUser->id,
                        'display_name' => $replyToUser->display_name,
                        'avatar_url' => $replyToUser->avatar_url,
                    ] : ['display_name' => 'Unknown'],
                ];
            }
        }

        // Pesan terhapus hanya soft-delete di database; baris dan isinya masih ada. Resource ini
        // dipakai SSR halaman room, HTTP, dan broadcast, jadi semua isi disaring di satu titik
        // ini. Yang tersisa hanya kerangka (id, pengirim, waktu, is_deleted) supaya frontend
        // bisa menampilkan penanda "pesan dihapus" tanpa membocorkan teks/lampirannya.
        $deleted = (bool) $this->is_deleted;

        return [
            'id' => $this->id,
            'body' => $deleted ? null : $this->body,
            'type' => $deleted ? 'text' : $this->type->value,
            'user' => $user ? [
                'id' => $user->id,
                'display_name' => $user->display_name,
                'avatar_url' => $user->avatar_url,
                'realm' => $realmBadge,
            ] : null,
            'attachments' => $deleted ? [] : $this->attachments,
            'reply_to' => $replyTo,
            'reply_to_id' => $this->reply_to_id,
            'reply_count' => (int) ($batch->replyCounts->get($this->id) ?? 0),
            'reactions' => $reactions,
            'marks' => [
                'helpful_count' => count($helpfulUserIds),
                'helpful_user_ids' => $helpfulUserIds,
                'is_best_answer' => $markRows->contains(fn ($row) => $row->kind === MarkKind::BestAnswer),
            ],
            'metadata' => $deleted ? null : $this->metadata,
            // Penanda "dikirim lewat kolom komentar chapter" — dipakai Discuss untuk memberi label
            // dan membuka chapter-nya. null = pesan chat biasa.
            'comment' => $this->comment_chapter_index !== null ? [
                'series_slug' => $this->comment_series_slug,
                'chapter_index' => (int) $this->comment_chapter_index,
            ] : null,
            'is_edited' => $this->is_edited,
            'is_deleted' => $this->is_deleted,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
