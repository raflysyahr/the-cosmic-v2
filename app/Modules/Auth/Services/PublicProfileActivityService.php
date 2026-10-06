<?php

namespace App\Modules\Auth\Services;

use App\Modules\Discuss\Enums\MessageType;
use App\Modules\Discuss\Enums\RoomType;
use App\Modules\Discuss\Models\Member;
use App\Modules\Discuss\Models\Message;
use App\Modules\Discuss\Models\Room;
use App\Modules\Discuss\Services\RoomService;

/**
 * Data tab di profil (Media / Links / Voice / Groups).
 *
 * Konten Media / Links / Voice dipisah menjadi dua scope:
 *  - group   : room non-direct. Tampil jika room PUBLIC, atau viewer sendiri
 *              anggota room tersebut (private / invite-only).
 *  - private : chat pribadi (1:1). Untuk profil orang lain hanya chat antara
 *              viewer dan pemilik profil. Untuk profil sendiri: semua chat
 *              pribadi miliknya. Chat pribadi orang lain tidak pernah bocor.
 */
class PublicProfileActivityService
{
    private const MEDIA_LIMIT = 60;
    private const LINK_LIMIT = 40;
    private const VOICE_LIMIT = 30;
    private const GROUP_LIMIT = 50;

    /** Ekstensi file yang dianggap pesan suara/audio (lihat FILE_RULES di SendMessageRequest). */
    private const AUDIO_EXT = ['mp3', 'wav', 'ogg'];

    public function __construct(private readonly RoomService $rooms) {}

    /**
     * @return array{
     *   media:  array{group: list<array<string,mixed>>, private: list<array<string,mixed>>},
     *   links:  array{group: list<array<string,mixed>>, private: list<array<string,mixed>>},
     *   voices: array{group: list<array<string,mixed>>, private: list<array<string,mixed>>},
     *   groups: list<array<string,mixed>>
     * }
     */
    public function forUser(string $targetId, ?string $viewerId): array
    {
        $groupRooms = $this->groupRooms($viewerId);
        $privateRooms = $this->privateRoomIds($targetId, $viewerId);

        return [
            'media' => [
                'group'   => $this->media($targetId, $groupRooms, true),
                'private' => $this->media($targetId, $privateRooms, false),
            ],
            'links' => [
                'group'   => $this->links($targetId, $groupRooms, true),
                'private' => $this->links($targetId, $privateRooms, false),
            ],
            'voices' => [
                'group'   => $this->voices($targetId, $groupRooms, true),
                'private' => $this->voices($targetId, $privateRooms, false),
            ],
            'groups' => $this->groups($targetId, $groupRooms),
        ];
    }

    /**
     * Subquery id room "group" yang boleh dilihat viewer.
     */
    private function groupRooms(?string $viewerId)
    {
        return Room::query()
            ->active()
            ->where(function ($q) {
                $q->whereNull('context_type')->orWhere('context_type', '!=', 'direct');
            })
            ->where(function ($q) use ($viewerId) {
                $q->where('type', RoomType::Public);
                if ($viewerId) {
                    $q->orWhereIn('id', Member::query()
                        ->where('user_id', $viewerId)
                        ->where('is_banned', false)
                        ->select('room_id'));
                }
            })
            ->select('id');
    }

    /**
     * @return list<string>
     */
    private function privateRoomIds(string $targetId, ?string $viewerId): array
    {
        if (! $viewerId) {
            return [];
        }

        if ($viewerId === $targetId) {
            return Room::query()
                ->active()
                ->where('context_type', 'direct')
                ->whereIn('id', Member::query()->where('user_id', $targetId)->select('room_id'))
                ->pluck('id')
                ->all();
        }

        $room = $this->rooms->findDirectChat($viewerId, $targetId);

        return $room && $room->is_active ? [$room->id] : [];
    }

    private function baseMessages(string $userId, $rooms)
    {
        return Message::query()
            ->where('user_id', $userId)
            ->notDeleted()
            ->whereIn('room_id', $rooms)
            ->orderByDesc('created_at');
    }

    /**
     * Tambahkan roomName (hanya untuk scope group) agar user tahu asal konten.
     *
     * @param  list<array<string,mixed>>  $items
     * @return list<array<string,mixed>>
     */
    private function withRoomNames(array $items, bool $include): array
    {
        if (! $include || $items === []) {
            return array_map(function ($i) {
                unset($i['roomId']);

                return $i;
            }, $items);
        }

        $names = Room::query()
            ->whereIn('id', array_unique(array_column($items, 'roomId')))
            ->pluck('name', 'id');

        return array_map(function ($i) use ($names) {
            $i['roomName'] = $names[$i['roomId']] ?? null;
            unset($i['roomId']);

            return $i;
        }, $items);
    }

    private function media(string $userId, $rooms, bool $withRoom): array
    {
        $items = $this->baseMessages($userId, $rooms)
            ->whereIn('type', [MessageType::Image->value, MessageType::Video->value])
            ->limit(self::MEDIA_LIMIT)
            ->get(['id', 'room_id', 'type', 'attachments', 'metadata', 'created_at'])
            ->map(function (Message $m) {
                $isVideo = $m->type === MessageType::Video;
                $url = $m->attachments[0] ?? null;
                if (! $url) {
                    return null;
                }

                return [
                    'id'        => $m->id,
                    'roomId'    => $m->room_id,
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

        return $this->withRoomNames($items, $withRoom);
    }

    private function links(string $userId, $rooms, bool $withRoom): array
    {
        $rows = $this->baseMessages($userId, $rooms)
            ->where('type', MessageType::Text->value)
            ->where('body', 'like', '%http%')
            ->limit(150)
            ->get(['id', 'room_id', 'body', 'created_at']);

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
                    'roomId'    => $m->room_id,
                    'url'       => $url,
                    'host'      => preg_replace('/^www\./i', '', $host),
                    'createdAt' => $m->created_at?->toIso8601String(),
                ];
                if (count($links) >= self::LINK_LIMIT) {
                    break 2;
                }
            }
        }

        return $this->withRoomNames($links, $withRoom);
    }

    private function voices(string $userId, $rooms, bool $withRoom): array
    {
        $items = $this->baseMessages($userId, $rooms)
            ->where('type', MessageType::File->value)
            ->limit(150)
            ->get(['id', 'room_id', 'attachments', 'metadata', 'created_at'])
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
                'roomId'    => $m->room_id,
                'url'       => $m->attachments[0],
                'name'      => $m->metadata['file']['name'] ?? 'Audio',
                'size'      => $m->metadata['file']['size'] ?? null,
                'createdAt' => $m->created_at?->toIso8601String(),
            ])
            ->values()
            ->all();

        return $this->withRoomNames($items, $withRoom);
    }

    private function groups(string $userId, $groupRooms): array
    {
        $roomIds = Member::query()
            ->where('user_id', $userId)
            ->where('is_banned', false)
            ->whereIn('room_id', $groupRooms)
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
