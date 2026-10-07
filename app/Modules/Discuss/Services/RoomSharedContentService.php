<?php

namespace App\Modules\Discuss\Services;

use App\Modules\Auth\Models\User;
use App\Modules\Discuss\Enums\MessageType;
use App\Modules\Discuss\Models\Member;
use App\Modules\Discuss\Models\Message;

/**
 * Konten bersama dalam SATU room untuk halaman About (tab Media / Links / Voice)
 * plus angka statistik room. Hanya membaca pesan room tersebut — otorisasi
 * (public vs member-only) sudah dijaga PageController::about().
 */
class RoomSharedContentService
{
    private const MEDIA_LIMIT = 90;
    private const LINK_LIMIT = 60;
    private const VOICE_LIMIT = 40;

    /** Ekstensi file yang dianggap pesan suara/audio (lihat FILE_RULES di SendMessageRequest). */
    private const AUDIO_EXT = ['mp3', 'wav', 'ogg'];

    /**
     * @return array{
     *   media: list<array<string,mixed>>,
     *   links: list<array<string,mixed>>,
     *   voices: list<array<string,mixed>>
     * }
     */
    public function forRoom(string $roomId): array
    {
        $media = $this->media($roomId);
        $links = $this->links($roomId);
        $voices = $this->voices($roomId);

        $senders = $this->senderNames(array_merge(
            array_column($media, 'userId'),
            array_column($links, 'userId'),
            array_column($voices, 'userId'),
        ));

        $attach = fn (array $items) => array_map(function ($i) use ($senders) {
            $i['senderName'] = $senders[$i['userId']] ?? null;
            unset($i['userId']);

            return $i;
        }, $items);

        return [
            'media'  => $attach($media),
            'links'  => $attach($links),
            'voices' => $attach($voices),
        ];
    }

    /**
     * @return array{members: int, messages: int, xp: int}
     */
    public function statsForRoom(string $roomId): array
    {
        return [
            'members'  => Member::where('room_id', $roomId)->where('is_banned', false)->count(),
            'messages' => Message::where('room_id', $roomId)->notDeleted()->count(),
            'xp'       => (int) Member::where('room_id', $roomId)->where('is_banned', false)->sum('xp_points'),
        ];
    }

    private function baseMessages(string $roomId)
    {
        return Message::query()
            ->where('room_id', $roomId)
            ->notDeleted()
            ->orderByDesc('created_at');
    }

    private function media(string $roomId): array
    {
        return $this->baseMessages($roomId)
            ->whereIn('type', [MessageType::Image->value, MessageType::Video->value])
            ->limit(self::MEDIA_LIMIT)
            ->get(['id', 'user_id', 'type', 'attachments', 'metadata', 'created_at'])
            ->map(function (Message $m) {
                $isVideo = $m->type === MessageType::Video;
                $url = $m->attachments[0] ?? null;
                if (! $url) {
                    return null;
                }

                return [
                    'id'        => $m->id,
                    'userId'    => $m->user_id,
                    'type'      => $isVideo ? 'video' : 'image',
                    'url'       => $url,
                    'thumbnail' => $isVideo
                        ? ($m->metadata['video']['thumbnail'] ?? null)
                        : ($m->metadata['thumbnail'] ?? $url),
                    'duration'  => $isVideo ? ($m->metadata['video']['duration'] ?? null) : null,
                    'createdAt' => $m->created_at?->toIso8601String(),
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    private function links(string $roomId): array
    {
        $rows = $this->baseMessages($roomId)
            ->where('type', MessageType::Text->value)
            ->where('body', 'like', '%http%')
            ->limit(200)
            ->get(['id', 'user_id', 'body', 'created_at']);

        $seen = [];
        $links = [];

        foreach ($rows as $m) {
            if (! preg_match_all('#https?://[^\s<>"\']+#i', (string) $m->body, $found)) {
                continue;
            }
            foreach ($found[0] as $raw) {
                $url = rtrim($raw, '.,;:!?)]}');
                $host = parse_url($url, PHP_URL_HOST);
                if (! $host || isset($seen[$url])) {
                    continue;
                }
                $seen[$url] = true;
                $links[] = [
                    'id'        => $m->id . '-' . count($links),
                    'userId'    => $m->user_id,
                    'url'       => $url,
                    'host'      => preg_replace('/^www\./i', '', $host),
                    'createdAt' => $m->created_at?->toIso8601String(),
                ];
                if (count($links) >= self::LINK_LIMIT) {
                    break 2;
                }
            }
        }

        return $links;
    }

    private function voices(string $roomId): array
    {
        return $this->baseMessages($roomId)
            ->where('type', MessageType::File->value)
            ->limit(200)
            ->get(['id', 'user_id', 'attachments', 'metadata', 'created_at'])
            ->filter(function (Message $m) {
                $file = $m->metadata['file'] ?? [];
                $mime = (string) ($file['mime'] ?? '');
                $ext = strtolower(pathinfo((string) ($file['name'] ?? ''), PATHINFO_EXTENSION));

                return ! empty($m->attachments[0])
                    && (str_starts_with($mime, 'audio/') || in_array($ext, self::AUDIO_EXT, true));
            })
            ->take(self::VOICE_LIMIT)
            ->map(fn (Message $m) => [
                'id'        => $m->id,
                'userId'    => $m->user_id,
                'url'       => $m->attachments[0],
                'name'      => $m->metadata['file']['name'] ?? 'Audio',
                'size'      => $m->metadata['file']['size'] ?? null,
                'createdAt' => $m->created_at?->toIso8601String(),
            ])
            ->values()
            ->all();
    }

    /**
     * @param  list<string>  $userIds
     * @return array<string, string>  userId => display_name
     */
    private function senderNames(array $userIds): array
    {
        $ids = array_values(array_unique(array_filter($userIds)));
        if ($ids === []) {
            return [];
        }

        return User::query()->whereIn('id', $ids)->pluck('display_name', 'id')->all();
    }
}
