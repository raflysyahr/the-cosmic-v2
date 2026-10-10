<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Post Story kini bisa berisi beberapa foto/video (carousel).
 *
 * Satu kolom JSON `media_items` (daftar berurutan) menggantikan empat kolom media
 * tunggal. Tiap item: id, type (image|video), url, thumbnail, width, height,
 * duration, name, size, mime, path, thumb_path (path = lokasi file di disk public,
 * dipakai untuk menghapus file). Media yang sudah ada dipindahkan sebagai item pertama.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('discuss_announcements', function (Blueprint $table) {
            $table->json('media_items')->nullable()->after('link_url');
        });

        DB::table('discuss_announcements')
            ->whereNotNull('media_url')
            ->orderBy('id')
            ->each(function ($row) {
                $meta = json_decode($row->media_meta ?? '[]', true) ?: [];

                $item = [
                    'id'         => (string) Str::ulid(),
                    'type'       => $row->media_type,
                    'url'        => $row->media_url,
                    'thumbnail'  => $row->media_thumbnail,
                    'width'      => $meta['width'] ?? null,
                    'height'     => $meta['height'] ?? null,
                    'duration'   => $meta['duration'] ?? null,
                    'name'       => $meta['name'] ?? null,
                    'size'       => $meta['size'] ?? null,
                    'mime'       => $meta['mime'] ?? null,
                    'path'       => $meta['path'] ?? null,
                    'thumb_path' => $meta['thumb_path'] ?? null,
                ];

                DB::table('discuss_announcements')
                    ->where('id', $row->id)
                    ->update(['media_items' => json_encode([$item])]);
            });

        Schema::table('discuss_announcements', function (Blueprint $table) {
            $table->dropColumn(['media_type', 'media_url', 'media_thumbnail', 'media_meta']);
        });
    }

    public function down(): void
    {
        Schema::table('discuss_announcements', function (Blueprint $table) {
            $table->string('media_type', 10)->nullable()->after('link_url');
            $table->text('media_url')->nullable()->after('media_type');
            $table->text('media_thumbnail')->nullable()->after('media_url');
            $table->json('media_meta')->nullable()->after('media_thumbnail');
        });

        // Kembali ke satu media: hanya item pertama yang dipertahankan.
        DB::table('discuss_announcements')
            ->whereNotNull('media_items')
            ->orderBy('id')
            ->each(function ($row) {
                $first = (json_decode($row->media_items, true) ?: [])[0] ?? null;
                if (! $first) {
                    return;
                }

                DB::table('discuss_announcements')->where('id', $row->id)->update([
                    'media_type'      => $first['type'],
                    'media_url'       => $first['url'],
                    'media_thumbnail' => $first['thumbnail'] ?? null,
                    'media_meta'      => json_encode(array_filter([
                        'name'       => $first['name'] ?? null,
                        'size'       => $first['size'] ?? null,
                        'mime'       => $first['mime'] ?? null,
                        'width'      => $first['width'] ?? null,
                        'height'     => $first['height'] ?? null,
                        'duration'   => $first['duration'] ?? null,
                        'path'       => $first['path'] ?? null,
                        'thumb_path' => $first['thumb_path'] ?? null,
                    ], fn ($v) => $v !== null)),
                ]);
            });

        Schema::table('discuss_announcements', function (Blueprint $table) {
            $table->dropColumn('media_items');
        });
    }
};
