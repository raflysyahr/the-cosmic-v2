<?php

namespace App\Modules\Discuss\Services;

use App\Modules\Auth\Enums\UserRole;
use App\Modules\Auth\Models\User;
use App\Modules\Discuss\Models\Announcement;
use App\Modules\Discuss\Models\AnnouncementReaction;
use App\Modules\Discuss\Models\AnnouncementSeen;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * Halaman Story = post dari admin platform (users.role = admin): teks, foto atau
 * video, dengan caption. User biasa membaca dan memberi reaksi; tidak ada
 * pembuatan story oleh user.
 * Semua otorisasi ada di sini (AGENTS.md §4), key error: "admin".
 */
class AnnouncementService
{
    // ------------------------------------------------------------- Pembaca

    /**
     * Post yang sudah tayang: yang disematkan dulu, lalu terbaru.
     *
     * @return list<array<string, mixed>>
     */
    public function listPublished(string $userId, int $limit = 30): array
    {
        $seenAt = $this->seenAt($userId);

        return $this->presentMany(
            Announcement::published()
                ->orderByDesc('is_pinned')
                ->orderByDesc('published_at')
                ->limit($limit)
                ->get(),
            $seenAt,
            $userId,
        );
    }

    public function unreadCount(string $userId): int
    {
        $seenAt = $this->seenAt($userId);

        return Announcement::published()
            ->when($seenAt, fn ($q) => $q->where('published_at', '>', $seenAt))
            ->count();
    }

    public function markSeen(string $userId): void
    {
        AnnouncementSeen::updateOrCreate(['user_id' => $userId], ['seen_at' => now()]);
    }

    // ------------------------------------------------------------- Reaksi

    /**
     * Beri / ganti / cabut reaksi. Satu reaksi per user per post: emoji yang sama
     * dengan reaksi sebelumnya = dicabut, emoji lain = mengganti.
     *
     * @return array{reactions: list<array{emoji: string, count: int}>, my_reaction: ?string, reaction_total: int}
     */
    public function react(string $userId, string $announcementId, string $emoji): array
    {
        if (! in_array($emoji, AnnouncementReaction::PALETTE, true)) {
            throw ValidationException::withMessages(['emoji' => ['That reaction is not available.']]);
        }

        // Hanya post yang sudah tayang yang bisa direaksi (yang terjadwal = 404).
        $announcement = Announcement::published()->findOrFail($announcementId);

        $existing = AnnouncementReaction::where('announcement_id', $announcement->id)
            ->where('user_id', $userId)
            ->first();

        if ($existing && $existing->emoji === $emoji) {
            $existing->delete();
        } elseif ($existing) {
            $existing->update(['emoji' => $emoji]);
        } else {
            AnnouncementReaction::create([
                'announcement_id' => $announcement->id,
                'user_id' => $userId,
                'emoji' => $emoji,
            ]);
        }

        return $this->reactionSummary([$announcement->id], $userId)[$announcement->id];
    }

    /**
     * Admin: siapa bereaksi apa pada satu post.
     *
     * @return array{total: int, reactions: list<array{emoji: string, count: int}>, reactors: list<array<string, mixed>>}
     */
    public function reactors(User $actor, string $announcementId): array
    {
        $this->assertAdmin($actor);

        $announcement = Announcement::findOrFail($announcementId);

        $rows = AnnouncementReaction::where('announcement_id', $announcement->id)
            ->orderByDesc('created_at')
            ->limit(300)
            ->get();

        $users = User::query()
            ->whereIn('id', $rows->pluck('user_id')->all())
            ->get(['id', 'username', 'display_name', 'avatar_url'])
            ->keyBy('id');

        $summary = $this->reactionSummary([$announcement->id], null)[$announcement->id];

        return [
            'total' => $summary['reaction_total'],
            'reactions' => $summary['reactions'],
            'reactors' => $rows->map(function (AnnouncementReaction $r) use ($users) {
                $u = $users->get($r->user_id);

                return [
                    'user_id' => $r->user_id,
                    'username' => $u?->username,
                    'display_name' => $u?->display_name ?? 'Deleted user',
                    'avatar_url' => $u?->avatar_url,
                    'emoji' => $r->emoji,
                    'reacted_at' => $r->created_at?->toIso8601String(),
                ];
            })->values()->all(),
        ];
    }

    // --------------------------------------------------------------- Admin

    /** @return list<array<string, mixed>> semua, termasuk yang terjadwal */
    public function listAll(User $actor): array
    {
        $this->assertAdmin($actor);

        return $this->presentMany(
            Announcement::orderByDesc('is_pinned')
                ->orderByDesc('published_at')
                ->limit(200)
                ->get(),
            null,
            (string) $actor->id,
        );
    }

    /** @param array<string, mixed> $data */
    public function create(User $actor, array $data, ?UploadedFile $media = null, ?UploadedFile $thumbnail = null): array
    {
        $this->assertAdmin($actor);

        $stored = $media ? $this->storeMedia($media, $thumbnail, $data) : null;

        $announcement = Announcement::create([
            // Post bermedia boleh tanpa judul/caption; kolom NOT NULL → string kosong.
            'title' => trim((string) ($data['title'] ?? '')),
            'body' => trim((string) ($data['body'] ?? '')),
            'link_url' => $this->blankToNull($data['link_url'] ?? null),
            'is_pinned' => (bool) ($data['is_pinned'] ?? false),
            // Kosong = tayang sekarang.
            'published_at' => ! empty($data['published_at']) ? Carbon::parse($data['published_at']) : now(),
            'created_by' => $actor->id,
        ] + ($stored ?? []));

        return $this->presentMany(collect([$announcement]), null, (string) $actor->id)[0];
    }

    /** @param array<string, mixed> $data */
    public function update(User $actor, string $id, array $data, ?UploadedFile $media = null, ?UploadedFile $thumbnail = null): array
    {
        $this->assertAdmin($actor);

        $announcement = Announcement::findOrFail($id);

        $attributes = [];
        foreach (['title', 'body'] as $key) {
            if (array_key_exists($key, $data)) {
                $attributes[$key] = trim((string) $data[$key]);
            }
        }
        if (array_key_exists('link_url', $data)) {
            $attributes['link_url'] = $this->blankToNull($data['link_url']);
        }
        if (array_key_exists('is_pinned', $data)) {
            $attributes['is_pinned'] = (bool) $data['is_pinned'];
        }
        if (array_key_exists('published_at', $data)) {
            $attributes['published_at'] = ! empty($data['published_at']) ? Carbon::parse($data['published_at']) : now();
        }

        $oldPaths = $this->mediaPaths($announcement);

        if ($media) {
            // Ganti media.
            $attributes += $this->storeMedia($media, $thumbnail, $data);
        } elseif (! empty($data['remove_media'])) {
            $attributes += ['media_type' => null, 'media_url' => null, 'media_thumbnail' => null, 'media_meta' => null];
        }

        // Post tidak boleh berakhir kosong (tanpa judul, caption, maupun media).
        $finalTitle = $attributes['title'] ?? $announcement->title;
        $finalBody = $attributes['body'] ?? $announcement->body;
        $finalMedia = array_key_exists('media_url', $attributes) ? $attributes['media_url'] : $announcement->media_url;
        if ($finalTitle === '' && $finalBody === '' && ! $finalMedia) {
            // File yang baru terlanjur tersimpan tidak boleh yatim.
            if ($media && isset($attributes['media_meta'])) {
                Storage::disk('public')->delete(array_values(array_filter([
                    $attributes['media_meta']['path'] ?? null,
                    $attributes['media_meta']['thumb_path'] ?? null,
                ])));
            }

            throw ValidationException::withMessages(['body' => ['A post needs text or a photo/video.']]);
        }

        $announcement->update($attributes);

        if (($media || ! empty($data['remove_media'])) && $oldPaths) {
            Storage::disk('public')->delete($oldPaths);
        }

        return $this->presentMany(collect([$announcement->fresh()]), null, (string) $actor->id)[0];
    }

    public function delete(User $actor, string $id): void
    {
        $this->assertAdmin($actor);

        $announcement = Announcement::findOrFail($id);
        $paths = $this->mediaPaths($announcement);

        AnnouncementReaction::where('announcement_id', $announcement->id)->delete();
        $announcement->delete();

        if ($paths) {
            Storage::disk('public')->delete($paths);
        }
    }

    // ------------------------------------------------------------- Internal

    /**
     * Simpan foto/video (+ poster video) di disk public. Durasi/ukuran video dibaca
     * di browser (server tanpa ffmpeg), sama seperti di chat.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function storeMedia(UploadedFile $media, ?UploadedFile $thumbnail, array $data): array
    {
        $isImage = str_starts_with((string) $media->getMimeType(), 'image/');

        $meta = [
            'name' => $media->getClientOriginalName(),
            'size' => $media->getSize(),
            'mime' => $media->getClientMimeType(),
            'width' => isset($data['width']) ? (int) $data['width'] : null,
            'height' => isset($data['height']) ? (int) $data['height'] : null,
        ];

        $path = $media->store($isImage ? 'story/images' : 'story/videos', 'public');
        $meta['path'] = $path;
        $thumbUrl = null;

        if (! $isImage) {
            $meta['duration'] = isset($data['duration']) ? round((float) $data['duration'], 2) : null;

            if ($thumbnail) {
                $thumbPath = $thumbnail->store('story/thumbs', 'public');
                $meta['thumb_path'] = $thumbPath;
                $thumbUrl = Storage::url($thumbPath);
            }
        }

        return [
            'media_type' => $isImage ? 'image' : 'video',
            'media_url' => Storage::url($path),
            'media_thumbnail' => $thumbUrl,
            'media_meta' => $meta,
        ];
    }

    /** @return list<string> path file di disk public yang dipakai post ini */
    private function mediaPaths(Announcement $announcement): array
    {
        $meta = $announcement->media_meta ?? [];

        return array_values(array_filter([$meta['path'] ?? null, $meta['thumb_path'] ?? null]));
    }

    /**
     * Batas "sudah dibaca". User yang belum pernah membuka Story dihitung
     * sejak tanggal ia bergabung — post lama sebelum ia mendaftar
     * tidak ditandai baru.
     */
    private function seenAt(string $userId): ?Carbon
    {
        $row = AnnouncementSeen::where('user_id', $userId)->first();
        if ($row) {
            return $row->seen_at;
        }

        return User::select('id', 'created_at')->where('id', $userId)->first()?->created_at;
    }

    /**
     * Ringkasan reaksi untuk banyak post sekaligus (2 query, bukan N+1).
     *
     * @param  list<string>  $ids
     * @return array<string, array{reactions: list<array{emoji: string, count: int}>, my_reaction: ?string, reaction_total: int}>
     */
    private function reactionSummary(array $ids, ?string $viewerId): array
    {
        $counts = AnnouncementReaction::query()
            ->whereIn('announcement_id', $ids)
            ->selectRaw('announcement_id, emoji, count(*) as total')
            ->groupBy('announcement_id', 'emoji')
            ->get()
            ->groupBy('announcement_id');

        $mine = $viewerId
            ? AnnouncementReaction::whereIn('announcement_id', $ids)->where('user_id', $viewerId)
                ->pluck('emoji', 'announcement_id')
            : collect();

        $out = [];
        foreach ($ids as $id) {
            $perEmoji = ($counts[$id] ?? collect())->pluck('total', 'emoji');

            $reactions = [];
            $total = 0;
            foreach (AnnouncementReaction::PALETTE as $emoji) {
                $count = (int) ($perEmoji[$emoji] ?? 0);
                if ($count > 0) {
                    $reactions[] = ['emoji' => $emoji, 'count' => $count];
                    $total += $count;
                }
            }

            $out[$id] = [
                'reactions' => $reactions,
                'my_reaction' => $mine[$id] ?? null,
                'reaction_total' => $total,
            ];
        }

        return $out;
    }

    /**
     * @param  \Illuminate\Support\Collection<int, Announcement>  $announcements
     * @return list<array<string, mixed>>
     */
    private function presentMany($announcements, ?Carbon $seenAt, string $viewerId): array
    {
        $summaries = $this->reactionSummary($announcements->pluck('id')->all(), $viewerId);

        return $announcements
            ->map(fn (Announcement $a) => $this->present($a, $seenAt, $summaries[$a->id]))
            ->values()
            ->all();
    }

    /**
     * @param  array{reactions: list<array{emoji: string, count: int}>, my_reaction: ?string, reaction_total: int}  $reactions
     * @return array<string, mixed>
     */
    private function present(Announcement $announcement, ?Carbon $seenAt, array $reactions): array
    {
        $meta = $announcement->media_meta ?? [];

        return [
            'id' => $announcement->id,
            'title' => $announcement->title,
            'body' => $announcement->body,
            'link_url' => $announcement->link_url,
            'is_pinned' => $announcement->is_pinned,
            'published_at' => $announcement->published_at?->toIso8601String(),
            'is_published' => $announcement->published_at !== null && $announcement->published_at <= now(),
            'is_new' => $seenAt !== null
                && $announcement->published_at !== null
                && $announcement->published_at > $seenAt,
            'media' => $announcement->media_url ? [
                'type' => $announcement->media_type,
                'url' => $announcement->media_url,
                'thumbnail' => $announcement->media_type === 'video'
                    ? $announcement->media_thumbnail
                    : $announcement->media_url,
                'duration' => $meta['duration'] ?? null,
                'width' => $meta['width'] ?? null,
                'height' => $meta['height'] ?? null,
            ] : null,
        ] + $reactions;
    }

    private function blankToNull(?string $value): ?string
    {
        $value = $value === null ? null : trim($value);

        return $value === '' ? null : $value;
    }

    private function assertAdmin(User $actor): void
    {
        if ($actor->role !== UserRole::Admin) {
            throw ValidationException::withMessages([
                'admin' => ['Only administrators can manage announcements.'],
            ]);
        }
    }
}
