<?php

namespace App\Services;

use App\Models\ReadingHistory;
use Illuminate\Support\Facades\Log;

class ReadingHistoryService
{
    public function __construct(
        private readonly KomikcastService $komikcastService,
    ) {}

    public function readChapters(string $userId, string $slug): array
    {
        $entry = ReadingHistory::where('user_id', $userId)
            ->where('slug', $slug)
            ->first();

        return $entry ? ($entry->read_chapters ?? []) : [];
    }

    public function recent(string $userId, int $limit = 20): array
    {
        $entries = ReadingHistory::where('user_id', $userId)
            ->whereNotNull('last_chapter_index')
            ->orderBy('updated_at', 'desc')
            ->limit($limit)
            ->get();

        // Refresh cover URLs from upstream (CDN URLs expire over time)
        $this->refreshCovers($entries);

        return $entries
            ->map(fn(ReadingHistory $h) => [
                'slug' => $h->slug,
                'title' => $h->title,
                'coverImage' => $h->cover_image ?? '',
                'chapterIndex' => $h->last_chapter_index,
                'chapterTitle' => "Chapter {$h->last_chapter_index}",
                'chapterUrl' => $h->last_chapter_url ?? "/series/{$h->slug}/chapter/{$h->last_chapter_index}",
                'timestamp' => $h->updated_at->timestamp * 1000,
            ])
            ->toArray();
    }

    /**
     * Refresh expired cover URLs by fetching fresh ones from upstream.
     * Updates DB in-place (self-healing) so subsequent calls are fast.
     */
    private function refreshCovers(\Illuminate\Database\Eloquent\Collection $entries): void
    {
        $slugs = $entries->pluck('slug')->unique()->values()->all();
        if (empty($slugs)) return;

        foreach ($slugs as $slug) {
            try {
                $detail = $this->komikcastService->getDetail($slug);
                if (($detail['success'] ?? false) && !empty($detail['data']['cover'])) {
                    $freshCover = $detail['data']['cover'];
                    // Update all entries with this slug
                    $entries->where('slug', $slug)->each(function ($entry) use ($freshCover) {
                        if ($entry->cover_image !== $freshCover) {
                            $entry->update(['cover_image' => $freshCover]);
                        }
                    });
                }
            } catch (\Exception $e) {
                Log::warning('Failed to refresh cover for slug: ' . $slug, [
                    'error' => $e->getMessage(),
                ]);
                // Fall back to stored cover — no crash
            }
        }
    }

    /**
     * Catat riwayat baca (dipakai fitur "continue reading" / read-set per
     * series) — TERPISAH TOTAL dari pemberian XP cultivation. XP diberikan
     * lewat endpoint lain (CultivationController::completeChapter) yang
     * baru dipanggil setelah syarat anti-curang di client terpenuhi
     * (semua gambar ter-load, scroll sampai bawah, durasi > 6 detik).
     * markRead() ini terpicu instan begitu chapter dibuka — itu memang
     * benar untuk riwayat baca, tapi TIDAK BOLEH lagi dipakai sebagai
     * sinyal pemberian XP.
     */
    public function markRead(
        string $userId,
        string $slug,
        ?string $title,
        ?string $coverImage,
        int $chapterIndex,
        ?string $chapterTitle = null,
        ?string $chapterUrl = null,
    ): ReadingHistory {
        $entry = ReadingHistory::updateOrCreate(
            ['user_id' => $userId, 'slug' => $slug],
            ['title' => $title, 'cover_image' => $coverImage],
        );

        $chapters = $entry->read_chapters ?? [];

        if (!in_array($chapterIndex, $chapters)) {
            $chapters[] = $chapterIndex;
            $entry->read_chapters = $chapters;
        }

        $entry->last_chapter_index = $chapterIndex;
        $entry->last_chapter_url = $chapterUrl;
        $entry->save();

        return $entry;
    }
}
