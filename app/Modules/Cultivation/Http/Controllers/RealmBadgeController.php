<?php

namespace App\Modules\Cultivation\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * Serve gambar badge realm dari storage PRIVAT
 * (storage/app/private/realm/{slug}.png).
 *
 * File di disk "local" tidak punya URL publik langsung, jadi harus
 * di-stream lewat controller ini. Pola sama dengan CoverController /
 * ChapterImageController (legacy, app/Http/Controllers/Api/V1/) tapi
 * ditulis sebagai PLAIN CLASS (bukan extends Controller) karena ini
 * modul baru yang wajib ikuti AGENTS.md.
 *
 * $slug di sini HARUS sudah dalam bentuk realmIconSlug() (lihat
 * CultivationService) — mis. "starlord" bukan "star-lord" — supaya
 * cocok dengan nama file asli yang di-upload.
 */
class RealmBadgeController
{
    public function __invoke(Request $request, string $slug)
    {
        // Cegah path traversal — slug tidak boleh mengandung '/' atau '..'
        if (str_contains($slug, '..') || str_contains($slug, '/')) {
            abort(404);
        }

        $path = "realm/{$slug}.png";

        if (! Storage::disk('local')->exists($path)) {
            abort(404);
        }

        return Storage::disk('local')->response($path);
    }
}
