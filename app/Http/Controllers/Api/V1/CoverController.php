<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * Serve gambar cover series dari storage PRIVAT
 * (storage/app/private/series/cover/{slug}.webp).
 *
 * File di disk "local" tidak punya URL publik langsung (beda dari disk
 * "public" yang di-symlink ke public/storage), jadi harus di-stream lewat
 * controller ini. URL publik yang dipakai frontend/database jadi:
 *   {APP_URL}/api/v1/series/{slug}/cover
 */
class CoverController extends Controller
{
    public function __invoke(Request $request, string $slug)
    {
        // Cegah path traversal — slug tidak boleh mengandung '/' atau '..'
        if (str_contains($slug, '..') || str_contains($slug, '/')) {
            abort(404);
        }

        $path = "series/cover/{$slug}.webp";

        if (! Storage::disk('local')->exists($path)) {
            abort(404);
        }

        return Storage::disk('local')->response($path);
    }
}
