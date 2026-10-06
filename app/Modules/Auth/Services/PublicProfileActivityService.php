<?php

namespace App\Modules\Auth\Services;

use App\Modules\Discuss\Enums\MessageType;
use App\Modules\Discuss\Models\Member;
use App\Modules\Discuss\Models\Message;
use App\Modules\Discuss\Models\Room;

/**
 * Data tab di profil publik (Media / Links / Voice / Groups).
 *
 * Privasi: hanya konten dari room PUBLIC yang aktif yang ditampilkan. Isi chat
 * direct / private / invite-only tidak pernah bocor ke profil orang lain.
 */
class PublicProfileActivityService
{
    private const MEDIA_LIMIT = 60;
    private const LINK_LIMIT = 40;
    private const VOICE_LIMIT = 30;
    private const GROUP_LIMIT = 50;

    /** Ekstensi file yang dianggap pesan suara/audio (lihat FILE_RULES di SendMessageRequest). */
    private const AUDIO_EXT = ['mp3', 'wav', 'ogg'];

    /**
     * @return array{media: list<array<string,mixed>>, links: list<array<string,mixed>>, voices: list<array<string,mixed>>, groups: list<array<string,mixed>>}
     */
    public function forUser(string $userId): array
    {
        $publicRooms = Room::query()->active()->public()->select('id');

        return [
            'media'  => $this->media($userId, $publicRooms),
            'links'  => $this->links($userId, $publicRooms),
            'voices' => $this->voices($userId, $publicRooms),
            'groups' => $this->groups($userId, $publicRooms),
        ];
    }

    private function baseMessages(string $userId, $publicRooms)
    {
        return Message::query()
            ->where('user_id', $userId)
            ->notDeleted()
            ->whereIn('room_id', $publicRooms)
            ->orderByDesc('created_at');
    }

    private function media(string $userId, $publicRooms): array
    {
        return $this->baseMessages($userId, $publicRooms)
            ->whereIn('type', [MessageType::Image->value, MessageType::Video->value])
            ->limit(self::MEDIA_LIMIT)
            ->get(['id', 'type', 'attachments', 'metadata', 'created_at'])
            ->map(function (Message $m) {
                $isVideo = $m->type === MessageType::Video;
                $url = $m->attachments[0] ?? null;
                if (! $url) {
                    return null;
                }

                return [
                    'id'        => $m->id,
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

    private function links(string $userId, $publicRooms): array
    {
        $rows = $this->baseMessages($userId, $publicRooms)
            ->where('type', MessageType::Text->value)
            ->where('body', 'like', '%http%')
            ->limit(150)
            ->get(['id', 'body', 'created_at']);

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

    private function voices(string $userId, $publicRooms): array
    {
        return $this->baseMessages($userId, $publicRooms)
            ->where('type', MessageType::File->value)
            ->limit(150)
            ->get(['id', 'attachments', 'metadata', 'created_at'])
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
                'url'       => $m->attachments[0],
                'name'      => $m->metadata['file']['name'] ?? 'Audio',
                'size'      => $m->metadata['file']['size'] ?? null,
                'createdAt' => $m->created_at?->toIso8601String(),
            ])
            ->values()
            ->all();
    }

    private function groups(string $userId, $publicRooms): array
    {
        $roomIds = Member::query()
            ->where('user_id', $userId)
            ->where('is_banned', false)
            ->whereIn('room_id', $publicRooms)
            ->limit(self::GROUP_LIMIT)
            ->pluck('room_id');

        if ($roomIds->isEmpty()) {
            return [];
        }

        $counts = Member::query()
            ->whereIn('room_id', $roomIds)
            ->selectRaw('room_id, count(*) as total')
            ->groupBy('room_id')
            ->pluck('total', 'room_id');

        return Room::query()
            ->whereIn('id', $roomIds)
            ->orderBy('name')
            ->get(['id', 'slug', 'name', 'cover_url'])
            ->map(fn (Room $r) => [
                'id'          => $r->id,
                'slug'        => $r->slug,
                'name'        => $r->name,
                'coverUrl'    => $r->cover_url,
                'memberCount' => (int) ($counts[$r->id] ?? 0),
            ])
            ->values()
            ->all();
    }
}
