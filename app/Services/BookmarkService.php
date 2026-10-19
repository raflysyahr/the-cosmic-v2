<?php

namespace App\Services;

use App\Models\Bookmark;
use App\Models\Series;
use Illuminate\Database\Eloquent\Collection;

class BookmarkService
{
    public function list(string $userId): Collection
    {
        $bookmarks = Bookmark::where('user_id', $userId)
            ->orderBy('created_at', 'desc')
            ->get();

        // Cover diambil dari tabel series lokal (satu query, tanpa jaringan). Hanya tampilan:
        // nilai tersimpan di bookmark tidak diubah, dan bila series tidak ada dipakai yang tersimpan.
        $covers = Series::coversFor($bookmarks->pluck('slug'));
        foreach ($bookmarks as $bookmark) {
            if (isset($covers[$bookmark->slug])) {
                $bookmark->cover_image = $covers[$bookmark->slug];
            }
        }

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
}
