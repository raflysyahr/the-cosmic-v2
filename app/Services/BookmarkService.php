<?php

namespace App\Services;

use App\Models\Bookmark;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;

class BookmarkService
{
    public function __construct(
        private readonly KomikcastService $komikcastService,
    ) {}

    public function list(string $userId): Collection
    {
        $bookmarks = Bookmark::where('user_id', $userId)
            ->orderBy('created_at', 'desc')
            ->get();

        // Refresh cover URLs from upstream (CDN URLs expire over time)
        $this->refreshCovers($bookmarks);

        return $bookmarks;
    }

    public function isBookmarked(string $userId, string $slug): bool
    {
        return Bookmark::where('user_id', $userId)
            ->where('slug', $slug)
            ->exists();
    }

    /**
     * Idempotent: bookmarking an already-bookmarked slug just returns the
     * existing row, matching the old client-side "add if not present"
     * behavior and the table's unique(user_id, slug) constraint.
     */
    public function add(string $userId, string $slug, string $title, ?string $coverImage, ?string $format): Bookmark
    {
        return Bookmark::firstOrCreate(
            ['user_id' => $userId, 'slug' => $slug],
            ['title' => $title, 'cover_image' => $coverImage, 'format' => $format],
        );
    }

    public function remove(string $userId, string $slug): void
    {
        Bookmark::where('user_id', $userId)
            ->where('slug', $slug)
            ->delete();
    }

    /**
     * Refresh expired cover URLs by fetching fresh ones from upstream.
     * Updates DB in-place (self-healing) so subsequent calls are fast.
     */
    private function refreshCovers(\Illuminate\Database\Eloquent\Collection $bookmarks): void
    {
        $slugs = $bookmarks->pluck('slug')->unique()->values()->all();
        if (empty($slugs)) return;

        foreach ($slugs as $slug) {
            try {
                $detail = $this->komikcastService->getDetail($slug);
                if (($detail['success'] ?? false) && !empty($detail['data']['cover'])) {
                    $freshCover = $detail['data']['cover'];
                    $bookmarks->where('slug', $slug)->each(function ($bookmark) use ($freshCover) {
                        if ($bookmark->cover_image !== $freshCover) {
                            $bookmark->update(['cover_image' => $freshCover]);
                        }
                    });
                }
            } catch (\Exception $e) {
                Log::warning('Failed to refresh bookmark cover for slug: ' . $slug, [
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }
}
