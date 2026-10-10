<?php

namespace App\Modules\Discuss\Services;

use GdImage;

/**
 * Membuat thumbnail JPEG ringan dari gambar (dipakai chat dan Story).
 *
 * Mengembalikan null bila thumbnail tidak bisa dibuat (ekstensi GD tidak ada,
 * file rusak, format tidak didukung GD, atau resolusi terlalu besar) — pemanggil
 * lalu memakai file asli sebagai gantinya.
 */
class ImageThumbnailService
{
    /** Batas piksel sumber (~50 MP) agar decode GD tidak menghabiskan memory_limit. */
    private const MAX_SOURCE_PIXELS = 50_000_000;

    public function make(string $binary, int $maxDim = 480, int $quality = 82): ?string
    {
        if (! function_exists('imagecreatefromstring')) {
            return null;
        }

        $size = @getimagesizefromstring($binary);
        if ($size === false || ($size[0] * $size[1]) > self::MAX_SOURCE_PIXELS) {
            return null;
        }

        $src = @imagecreatefromstring($binary);
        if ($src === false) {
            return null;
        }

        // Foto dari HP menyimpan arah rotasi di EXIF. Browser menerapkannya pada
        // file asli, tetapi hasil re-encode GD tidak membawa EXIF — jadi putar di sini.
        $src = $this->applyExifOrientation($src, $binary);

        $srcW = imagesx($src);
        $srcH = imagesy($src);

        $ratio = min(1.0, $maxDim / max($srcW, $srcH));
        $dstW = max(1, (int) round($srcW * $ratio));
        $dstH = max(1, (int) round($srcH * $ratio));

        $dst = imagecreatetruecolor($dstW, $dstH);
        // JPEG tidak punya alpha — ratakan transparansi ke putih dulu.
        imagefill($dst, 0, 0, (int) imagecolorallocate($dst, 255, 255, 255));
        imagecopyresampled($dst, $src, 0, 0, 0, 0, $dstW, $dstH, $srcW, $srcH);

        ob_start();
        imagejpeg($dst, null, $quality);
        $encoded = ob_get_clean();

        imagedestroy($src);
        imagedestroy($dst);

        return $encoded !== false && $encoded !== '' ? $encoded : null;
    }

    private function applyExifOrientation(GdImage $image, string $binary): GdImage
    {
        // Hanya JPEG yang membawa EXIF orientation.
        if (! function_exists('exif_read_data') || ! str_starts_with($binary, "\xFF\xD8")) {
            return $image;
        }

        $exif = @exif_read_data('data://image/jpeg;base64,' . base64_encode($binary));
        $orientation = (int) ($exif['Orientation'] ?? 1);

        if ($orientation === 2) {
            imageflip($image, IMG_FLIP_HORIZONTAL);
        } elseif ($orientation === 4) {
            imageflip($image, IMG_FLIP_VERTICAL);
        }

        $rotated = match ($orientation) {
            3 => imagerotate($image, 180, 0),
            6 => imagerotate($image, -90, 0),
            8 => imagerotate($image, 90, 0),
            default => null,
        };

        if ($rotated instanceof GdImage) {
            imagedestroy($image);

            return $rotated;
        }

        return $image;
    }
}
