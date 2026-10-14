<?php

namespace App\Modules\Discuss\Console;

use App\Modules\Discuss\Models\Announcement;
use App\Modules\Discuss\Services\AnnouncementService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Buat thumbnail untuk foto Story yang diunggah sebelum fitur thumbnail ada.
 * Aman dijalankan berulang: hanya memproses foto yang belum punya thumbnail
 * (GIF dilewati agar animasinya tidak hilang).
 */
class GenerateStoryThumbnails extends Command
{
    protected $signature = 'discuss:story-thumbnails';

    protected $description = 'Generate missing thumbnails for existing Story photos';

    public function handle(AnnouncementService $announcements): int
    {
        $disk = Storage::disk('public');
        $made = 0;
        $skipped = 0;

        Announcement::query()->whereNotNull('media_items')->orderBy('id')->each(function (Announcement $post) use ($announcements, $disk, &$made, &$skipped) {
            $items = $post->media_items ?? [];
            $changed = false;

            foreach ($items as $i => $item) {
                $needs = ($item['type'] ?? null) === 'image'
                    && empty($item['thumbnail'])
                    && ($item['mime'] ?? '') !== 'image/gif'
                    && ! empty($item['path']);

                if (! $needs) {
                    continue;
                }

                if (! $disk->exists($item['path']) || ! $announcements->attachImageThumbnail($item, $disk->get($item['path']))) {
                    $skipped++;

                    continue;
                }

                $items[$i] = $item;
                $changed = true;
                $made++;
            }

            if ($changed) {
                $post->update(['media_items' => $items]);
            }
        });

        $this->info("Thumbnails created: {$made}. Skipped (missing file or unsupported): {$skipped}.");

        return self::SUCCESS;
    }
}
