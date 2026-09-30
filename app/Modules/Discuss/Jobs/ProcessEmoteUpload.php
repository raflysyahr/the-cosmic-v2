<?php

namespace App\Modules\Discuss\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class ProcessEmoteUpload implements ShouldQueue
{
    use Dispatchable, Queueable;

    public function __construct(
        private readonly UploadedFile $file,
        private readonly string $emoteId,
    ) {}

    public function handle(): void
    {
        $image = imagecreatefromstring(file_get_contents($this->file->path()));

        if (! $image) {
            return;
        }

        $resized = imagescale($image, 64, 64);

        if (! $resized) {
            imagedestroy($image);
            return;
        }

        $tempPath = sys_get_temp_dir() . '/' . $this->emoteId . '.png';
        imagepng($resized, $tempPath);

        Storage::put(
            'emotes/' . $this->emoteId . '.png',
            file_get_contents($tempPath),
            'public',
        );

        imagedestroy($image);
        imagedestroy($resized);
        unlink($tempPath);
    }
}
