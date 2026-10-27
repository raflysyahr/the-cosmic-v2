<?php

namespace App\Services;

use App\Models\Chapter;
use App\Models\ChapterReaction;
use App\Models\Series;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Validation\ValidationException;

/**
 * Reaksi untuk halaman chapter. Satu reaksi per user per chapter: menekan reaksi yang sama
 * mencabutnya, reaksi lain menggantinya. Tidak ada hubungannya dengan reaksi pesan Discuss.
 */
class ChapterReactionService
{
    /** Urutan = urutan tampil. Emoji-nya dipetakan di frontend (Components/reader/ChapterReactions.tsx). */
    public const KEYS = ['like', 'love', 'laugh', 'wow', 'sad', 'fire'];

    /** Komik + chapter harus ada (404 bila tidak). */
    public function assertChapterExists(string $seriesSlug, int $chapterIndex): void
    {
        $seriesId = Series::where('slug', $seriesSlug)->value('id');
        abort_unless($seriesId, 404);

        abort_unless(
            Chapter::where('series_id', $seriesId)->where('index', $chapterIndex)->exists(),
            404,
        );
    }

    /**
     * @return array{reactions: list<array{key: string, count: int}>, my_reaction: ?string, total: int}
     */
    public function summary(string $seriesSlug, int $chapterIndex, ?string $viewerId): array
    {
        $counts = ChapterReaction::where('series_slug', $seriesSlug)
            ->where('chapter_index', $chapterIndex)
            ->selectRaw('reaction, count(*) as total')
            ->groupBy('reaction')
            ->pluck('total', 'reaction');

        $reactions = [];
        $total = 0;
        foreach (self::KEYS as $key) {
            $count = (int) ($counts[$key] ?? 0);
            if ($count > 0) {
                $reactions[] = ['key' => $key, 'count' => $count];
                $total += $count;
            }
        }

        $mine = $viewerId
            ? ChapterReaction::where('series_slug', $seriesSlug)
                ->where('chapter_index', $chapterIndex)
                ->where('user_id', $viewerId)
                ->value('reaction')
            : null;

        return ['reactions' => $reactions, 'my_reaction' => $mine, 'total' => $total];
    }

    /**
     * @return array{reactions: list<array{key: string, count: int}>, my_reaction: ?string, total: int}
     */
    public function toggle(string $userId, string $seriesSlug, int $chapterIndex, string $key): array
    {
        if (! in_array($key, self::KEYS, true)) {
            throw ValidationException::withMessages(['reaction' => ['That reaction is not available.']]);
        }

        $this->assertChapterExists($seriesSlug, $chapterIndex);

        $scope = fn () => ChapterReaction::where('series_slug', $seriesSlug)
            ->where('chapter_index', $chapterIndex)
            ->where('user_id', $userId);

        $existing = $scope()->first();

        if ($existing && $existing->reaction === $key) {
            $existing->delete();
        } elseif ($existing) {
            $existing->update(['reaction' => $key]);
        } else {
            try {
                ChapterReaction::create([
                    'series_slug' => $seriesSlug,
                    'chapter_index' => $chapterIndex,
                    'user_id' => $userId,
                    'reaction' => $key,
                ]);
            } catch (UniqueConstraintViolationException) {
                // Dua klik bersamaan dari user yang sama: yang kalah cukup menimpa pilihannya.
                $scope()->update(['reaction' => $key]);
            }
        }

        return $this->summary($seriesSlug, $chapterIndex, $userId);
    }
}
