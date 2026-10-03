<?php

namespace App\Modules\Discuss\Services;

use App\Modules\Auth\Enums\UserRole;
use App\Modules\Auth\Models\User;
use App\Modules\Discuss\Models\Announcement;
use App\Modules\Discuss\Models\AnnouncementSeen;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * Halaman Story = pemberitahuan dari admin platform (users.role = admin).
 * User biasa hanya membaca; tidak ada pembuatan story oleh user.
 * Semua otorisasi ada di sini (AGENTS.md §4), key error: "admin".
 */
class AnnouncementService
{
    // ------------------------------------------------------------- Pembaca

    /**
     * Pemberitahuan yang sudah tayang: yang disematkan dulu, lalu terbaru.
     *
     * @return list<array<string, mixed>>
     */
    public function listPublished(string $userId, int $limit = 30): array
    {
        $seenAt = $this->seenAt($userId);

        return Announcement::published()
            ->orderByDesc('is_pinned')
            ->orderByDesc('published_at')
            ->limit($limit)
            ->get()
            ->map(fn (Announcement $a) => $this->present($a, $seenAt))
            ->values()
            ->all();
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

    // --------------------------------------------------------------- Admin

    /** @return list<array<string, mixed>> semua, termasuk yang terjadwal */
    public function listAll(User $actor): array
    {
        $this->assertAdmin($actor);

        return Announcement::orderByDesc('is_pinned')
            ->orderByDesc('published_at')
            ->limit(200)
            ->get()
            ->map(fn (Announcement $a) => $this->present($a, null))
            ->values()
            ->all();
    }

    /** @param array<string, mixed> $data */
    public function create(User $actor, array $data): array
    {
        $this->assertAdmin($actor);

        $announcement = Announcement::create([
            'title' => trim($data['title']),
            'body' => trim($data['body']),
            'link_url' => $this->blankToNull($data['link_url'] ?? null),
            'is_pinned' => (bool) ($data['is_pinned'] ?? false),
            // Kosong = tayang sekarang.
            'published_at' => ! empty($data['published_at']) ? Carbon::parse($data['published_at']) : now(),
            'created_by' => $actor->id,
        ]);

        return $this->present($announcement, null);
    }

    /** @param array<string, mixed> $data */
    public function update(User $actor, string $id, array $data): array
    {
        $this->assertAdmin($actor);

        $announcement = Announcement::findOrFail($id);

        $attributes = [];
        foreach (['title', 'body'] as $key) {
            if (array_key_exists($key, $data)) {
                $attributes[$key] = trim($data[$key]);
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

        $announcement->update($attributes);

        return $this->present($announcement->fresh(), null);
    }

    public function delete(User $actor, string $id): void
    {
        $this->assertAdmin($actor);

        Announcement::findOrFail($id)->delete();
    }

    // ------------------------------------------------------------- Internal

    /**
     * Batas "sudah dibaca". User yang belum pernah membuka Story dihitung
     * sejak tanggal ia bergabung — pemberitahuan lama sebelum ia mendaftar
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

    /** @return array<string, mixed> */
    private function present(Announcement $announcement, ?Carbon $seenAt): array
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
        ];
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
