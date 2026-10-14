<?php

namespace App\Modules\Discuss\Services;

use App\Modules\Auth\Enums\UserRole;
use App\Modules\Auth\Models\User;
use App\Modules\Discuss\Models\Announcement;
use App\Modules\Discuss\Models\AnnouncementReaction;
use App\Modules\Discuss\Models\AnnouncementSeen;
use App\Modules\Discuss\Http\Requests\AnnouncementRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Halaman Story = post dari admin platform (users.role = admin): teks atau carousel
 * foto/video (sampai 10 per post), dengan caption. User biasa membaca dan memberi
 * reaksi; tidak ada pembuatan story oleh user.
 * Semua otorisasi ada di sini (AGENTS.md §4), key error: "admin".
 */
class AnnouncementService
{
    /** Sisi terpanjang thumbnail foto di feed (px). Layar HP ~3x DPR ≈ 1080 px. */
    private const FEED_THUMB_PX = 1080;

    public function __construct(private readonly ImageThumbnailService $thumbnails) {}

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

    /**
     * @param  array<string, mixed>  $data
     * @param  array<int|string, UploadedFile>  $files  media[i]
     * @param  array<int|string, UploadedFile>  $thumbnails  thumbnails[i] (poster video dari browser)
     */
    public function create(User $actor, array $data, array $files = [], array $thumbnails = []): array
    {
        $this->assertAdmin($actor);

        $items = $this->storeItems($files, $thumbnails, $data);

        $announcement = Announcement::create([
            // Post bermedia boleh tanpa judul/caption; kolom NOT NULL → string kosong.
            'title' => trim((string) ($data['title'] ?? '')),
            'body' => trim((string) ($data['body'] ?? '')),
            'link_url' => $this->blankToNull($data['link_url'] ?? null),
            'is_pinned' => (bool) ($data['is_pinned'] ?? false),
            // Kosong = tayang sekarang.
            'published_at' => ! empty($data['published_at']) ? Carbon::parse($data['published_at']) : now(),
            'created_by' => $actor->id,
            'media_items' => $items ?: null,
        ]);

        return $this->presentMany(collect([$announcement]), null, (string) $actor->id)[0];
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<int|string, UploadedFile>  $files
     * @param  array<int|string, UploadedFile>  $thumbnails
     */
    public function update(User $actor, string $id, array $data, array $files = [], array $thumbnails = []): array
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

        // --- Media: pertahankan sebagian item lama (urutan mengikuti keep_media),
        //     lalu tambahkan file baru di akhir.
        $current = $announcement->media_items ?? [];
        $kept = $current;
        $removed = [];

        if (! empty($data['sync_media'])) {
            $byId = collect($current)->keyBy('id');
            $kept = [];
            foreach ((array) ($data['keep_media'] ?? []) as $keepId) {
                if ($byId->has($keepId) && ! isset($kept[$keepId])) {
                    $kept[$keepId] = $byId[$keepId];
                }
            }
            $kept = array_values($kept);
            $removed = array_values(array_filter($current, fn ($item) => ! in_array($item['id'], array_column($kept, 'id'), true)));
        }

        $added = $this->storeItems($files, $thumbnails, $data);

        if (count($kept) + count($added) > AnnouncementRequest::MAX_MEDIA) {
            $this->deleteFiles($this->itemPaths($added)); // jangan tinggalkan file yatim
            throw ValidationException::withMessages([
                'media' => ['A post can have at most ' . AnnouncementRequest::MAX_MEDIA . ' photos/videos.'],
            ]);
        }

        $finalItems = array_values(array_merge($kept, $added));
        if (! empty($data['sync_media']) || $added !== []) {
            $attributes['media_items'] = $finalItems ?: null;
        }

        // Post tidak boleh berakhir kosong (tanpa judul, caption, maupun media).
        $finalTitle = $attributes['title'] ?? $announcement->title;
        $finalBody = $attributes['body'] ?? $announcement->body;
        if ($finalTitle === '' && $finalBody === '' && $finalItems === []) {
            $this->deleteFiles($this->itemPaths($added));
            throw ValidationException::withMessages(['body' => ['A post needs text or a photo/video.']]);
        }

        $announcement->update($attributes);

        // File item yang dibuang baru dihapus setelah update berhasil.
        $this->deleteFiles($this->itemPaths($removed));

        return $this->presentMany(collect([$announcement->fresh()]), null, (string) $actor->id)[0];
    }

    public function delete(User $actor, string $id): void
    {
        $this->assertAdmin($actor);

        $announcement = Announcement::findOrFail($id);
        $paths = $this->itemPaths($announcement->media_items ?? []);

        AnnouncementReaction::where('announcement_id', $announcement->id)->delete();
        $announcement->delete();

        $this->deleteFiles($paths);
    }

    // ------------------------------------------------------------- Internal

    /**
     * Simpan semua file upload sebagai item carousel (urutan = urutan indeks).
     *
     * @param  array<int|string, UploadedFile>  $files
     * @param  array<int|string, UploadedFile>  $thumbnails
     * @param  array<string, mixed>  $data
     * @return list<array<string, mixed>>
     */
    private function storeItems(array $files, array $thumbnails, array $data): array
    {
        ksort($files);
        $items = [];

        try {
            foreach ($files as $index => $file) {
                if (! $file instanceof UploadedFile) {
                    continue;
                }

                $items[] = $this->storeItem($file, $thumbnails[$index] ?? null, [
                    'width' => $data['widths'][$index] ?? null,
                    'height' => $data['heights'][$index] ?? null,
                    'duration' => $data['durations'][$index] ?? null,
                ]);
            }
        } catch (\Throwable $e) {
            $this->deleteFiles($this->itemPaths($items));
            throw $e;
        }

        return $items;
    }

    /**
     * Simpan satu foto/video di disk public. Foto dibuatkan thumbnail JPEG (kecuali GIF,
     * agar animasinya tidak hilang); video memakai poster dari browser. Durasi/ukuran
     * dibaca di browser (server tanpa ffmpeg), sama seperti di chat.
     *
     * @param  array{width: mixed, height: mixed, duration: mixed}  $meta
     * @return array<string, mixed>
     */
    private function storeItem(UploadedFile $file, ?UploadedFile $poster, array $meta): array
    {
        $mime = (string) $file->getMimeType();
        $isImage = str_starts_with($mime, 'image/');

        $path = $file->store($isImage ? 'story/images' : 'story/videos', 'public');

        $item = [
            'id' => (string) Str::ulid(),
            'type' => $isImage ? 'image' : 'video',
            'url' => Storage::url($path),
            'thumbnail' => null,
            'width' => isset($meta['width']) ? (int) $meta['width'] : null,
            'height' => isset($meta['height']) ? (int) $meta['height'] : null,
            'duration' => null,
            'name' => $file->getClientOriginalName(),
            'size' => $file->getSize(),
            'mime' => $file->getClientMimeType(),
            'path' => $path,
            'thumb_path' => null,
        ];

        if ($isImage) {
            if ($mime !== 'image/gif') {
                $this->attachImageThumbnail($item, $file->get());
            }
        } else {
            $item['duration'] = isset($meta['duration']) ? round((float) $meta['duration'], 2) : null;

            if ($poster) {
                $posterPath = $poster->store('story/thumbs', 'public');
                $item['thumb_path'] = $posterPath;
                $item['thumbnail'] = Storage::url($posterPath);
            }
        }

        return $item;
    }

    /**
     * Buat thumbnail feed untuk item foto. Tidak melakukan apa pun bila thumbnail tidak
     * bisa dibuat (feed lalu memakai file asli).
     *
     * @param  array<string, mixed>  $item  diubah lewat referensi
     */
    public function attachImageThumbnail(array &$item, string $binary): bool
    {
        $thumb = $this->thumbnails->make($binary, self::FEED_THUMB_PX, 84);
        if ($thumb === null) {
            return false;
        }

        $thumbPath = 'story/thumbs/' . pathinfo((string) $item['path'], PATHINFO_FILENAME) . '.jpg';
        Storage::disk('public')->put($thumbPath, $thumb);

        $item['thumb_path'] = $thumbPath;
        $item['thumbnail'] = Storage::url($thumbPath);

        return true;
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @return list<string> path file di disk public yang dipakai item-item ini
     */
    private function itemPaths(array $items): array
    {
        $paths = [];
        foreach ($items as $item) {
            foreach (['path', 'thumb_path'] as $key) {
                if (! empty($item[$key])) {
                    $paths[] = $item[$key];
                }
            }
        }

        return $paths;
    }

    /** @param list<string> $paths */
    private function deleteFiles(array $paths): void
    {
        if ($paths) {
            Storage::disk('public')->delete($paths);
        }
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
            // Carousel: urutan tampil. Foto tanpa thumbnail (mis. GIF) memakai file asli.
            'media' => array_map(fn (array $item) => [
                'id' => $item['id'],
                'type' => $item['type'],
                'url' => $item['url'],
                'thumbnail' => $item['thumbnail'] ?? ($item['type'] === 'image' ? $item['url'] : null),
                'duration' => $item['duration'] ?? null,
                'width' => $item['width'] ?? null,
                'height' => $item['height'] ?? null,
            ], $announcement->media_items ?? []),
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
