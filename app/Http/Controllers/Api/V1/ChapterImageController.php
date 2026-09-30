<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Serve gambar halaman chapter dari storage PRIVAT
 * (storage/app/private/series/images/{slug}/{index}/{filename}).
 *
 * File di disk "local" tidak punya URL publik langsung (beda dari disk
 * "public" yang di-symlink ke public/storage), jadi harus di-stream lewat
 * controller ini. URL publik yang dipakai frontend/database jadi:
 *   {APP_URL}/api/v1/series/{slug}/chapters/{index}/images/{filename}
 */
class ChapterImageController extends Controller
{
    public function __invoke(Request $request, string $slug, string $index, string $filename)
    {
        // Cegah path traversal — filename tidak boleh mengandung '/' atau '..'
        if (str_contains($filename, '..') || str_contains($filename, '/')) {
            abort(404);
        }

        $path = "series/images/{$slug}/{$index}/{$filename}";

        if (! Storage::disk('local')->exists($path)) {
            abort(404);
        }

        return Storage::disk('local')->response($path);
    }
}
