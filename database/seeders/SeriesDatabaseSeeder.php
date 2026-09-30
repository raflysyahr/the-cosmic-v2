<?php

namespace Database\Seeders;

use App\Models\Chapter;
use App\Models\ChapterPage;
use App\Models\Genre;
use App\Models\Series;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Mengisi tabel genres, series, genre_series, chapters, dan chapter_pages
 * dari data mock-api (raw response asli server Komikcast, dibungkus
 * {status, message, data}) yang disalin ke database/seeders/data/.
 *
 * Sumber data:
 *   database/seeders/data/series/{slug}.json
 *   database/seeders/data/chapters/{slug}.json
 *   database/seeders/data/chapters-detail/{slug}/{index}.json
 *
 * Gambar chapter (kolom `images`) DISIMPAN DI TABEL TERPISAH
 * `chapter_pages` (relasi 1:1 dengan `chapters`, key = chapter_id) —
 * bukan langsung di tabel `chapters` — supaya query list chapter
 * (Series::chapters(), dipakai halaman Detail) tidak ikut nge-load array
 * gambar yang bisa berisi ratusan URL per chapter. Gambar baru dibuka
 * saat Reader akses 1 chapter spesifik lewat Chapter::pages().
 *
 * Kalau storage/app/private/series/images/{slug}/{index}/ berisi file
 * .jpg/.png dst, dipakai URL LOKAL yang mengarah ke route API
 * ChapterImageController. Kalau folder itu belum ada/kosong, fallback ke
 * URL eksternal dari chapters-detail/{slug}/{index}.json. Taruh file
 * fisik di storage/app/private/series/images/{slug}/{index}/001.jpg dst
 * SEBELUM menjalankan seeder ini kalau mau gambar lokal langsung kepakai
 * — seeder aman dijalankan ulang kapan saja (pakai updateOrCreate).
 *
 * Cover series (kolom `cover`) mengikuti pola yang sama: kalau
 * storage/app/private/series/cover/{slug}.webp ada, dipakai URL lokal
 * lewat route CoverController; kalau belum ada, fallback ke URL cloud
 * dari `coverImage` di raw data series.
 *
 * Jalankan: php artisan db:seed --class=SeriesDatabaseSeeder
 * (atau otomatis lewat DatabaseSeeder kalau sudah didaftarkan di sana)
 */
class SeriesDatabaseSeeder extends Seeder
{
    protected string $dataPath;

    /** Counter untuk ringkasan di akhir seeding: berapa chapter pakai gambar lokal vs fallback cloud. */
    protected int $imagesFromLocal = 0;

    protected int $imagesFromCloud = 0;

    protected int $coversFromLocal = 0;

    protected int $coversFromCloud = 0;

    public function __construct()
    {
        $this->dataPath = database_path('seeders/data');
    }

    public function run(): void
    {
        $seriesDir = "{$this->dataPath}/series";

        if (! File::isDirectory($seriesDir)) {
            $this->command?->warn("Folder data tidak ditemukan: {$seriesDir} — seeder dilewati.");

            return;
        }

        $files = File::files($seriesDir);
        $this->command?->info('Seeding '.count($files).' series...');

        foreach ($files as $file) {
            $slug = $file->getFilenameWithoutExtension();
            $raw = $this->readJson($file->getPathname());

            if (! $raw) {
                continue;
            }

            $seriesRaw = $raw['data'] ?? null; // unwrap {status, message, data}
            if (! $seriesRaw) {
                continue;
            }

            $d = $seriesRaw['data'] ?? [];

            $series = Series::updateOrCreate(
                ['slug' => $d['slug'] ?? $slug],
                [
                    'title' => $d['title'] ?? $slug,
                    'native_title' => $d['nativeTitle'] ?? null,
                    'cover' => $this->resolveCoverUrl($d['slug'] ?? $slug, $d['coverImage'] ?? null),
                    'rating' => $d['rating'] ?? 0,
                    'status' => $d['status'] ?? 'ongoing',
                    'type' => $d['format'] ?? null,
                    'is_hot' => (bool) ($d['isHot'] ?? false),
                    'total_chapters' => (int) ($d['totalChapters'] ?? 0),
                    'author' => $d['author'] ?? null,
                    'anime_adaptation' => (bool) ($d['animeAdaptation'] ?? false),
                    'synopsis' => $d['synopsis'] ?? null,
                    'views' => (int) ($seriesRaw['dataMetadata']['totalViews'] ?? 0),
                    'released_at' => $seriesRaw['createdAt'] ?? null,
                ]
            );

            // --- Genre embedded di dalam data.genres[] -------------------
            $genreIds = [];
            foreach ($d['genres'] ?? [] as $g) {
                $name = $g['data']['name'] ?? null;
                if (! $name) {
                    continue;
                }

                $genre = Genre::updateOrCreate(
                    ['slug' => Str::slug($name)],
                    [
                        'name' => $name,
                        'description' => $g['data']['description'] ?? null,
                    ]
                );

                $genreIds[] = $genre->id;
            }
            if ($genreIds) {
                $series->genres()->syncWithoutDetaching($genreIds);
            }

            // --- Chapters list untuk series ini ---------------------------
            $this->seedChapters($series, $slug);
        }

        $this->command?->info("Gambar chapter: {$this->imagesFromLocal} chapter pakai storage lokal, {$this->imagesFromCloud} chapter fallback ke URL cloud (folder belum ada/kosong).");
        $this->command?->info("Cover series: {$this->coversFromLocal} series pakai storage lokal, {$this->coversFromCloud} series fallback ke URL cloud (file belum ada).");
        $this->command?->info('Seeding series selesai.');
    }

    protected function seedChapters(Series $series, string $slug): void
    {
        $chaptersFile = "{$this->dataPath}/chapters/{$slug}.json";
        $raw = $this->readJson($chaptersFile);
        if (! $raw) {
            return;
        }

        $list = $raw['data'] ?? []; // unwrap {status, message, data: [...]}

        foreach ($list as $c) {
            $cd = $c['data'] ?? [];
            $index = $cd['index'] ?? null;
            if ($index === null) {
                continue;
            }

            // Ambil gambar chapter — utamakan file lokal di storage privat
            // storage/app/private/series/images/{slug}/{index}/, fallback
            // ke `images` dari chapters-detail/{slug}/{index}.json (URL
            // eksternal asli) kalau folder privat itu belum ada isinya.
            $detailFile = "{$this->dataPath}/chapters-detail/{$slug}/{$index}.json";
            $detailRaw = $this->readJson($detailFile);
            $originalImages = $detailRaw['data']['data']['images'] ?? [];
            $images = $this->resolveChapterImages($slug, $index, $originalImages);

            $chapter = Chapter::updateOrCreate(
                ['series_id' => $series->id, 'index' => $index],
                [
                    'title' => $cd['title'] ?? "Chapter {$index}",
                    'released_at' => $c['createdAt'] ?? null,
                ]
            );

            // images disimpan terpisah di tabel chapter_pages (1:1) —
            // supaya query list chapter (Series::chapters()) tidak ikut
            // nge-load array gambar yang bisa berisi ratusan URL.
            ChapterPage::updateOrCreate(
                ['chapter_id' => $chapter->id],
                ['images' => $images]
            );
        }
    }

    /**
     * Kalau storage/app/private/series/cover/{slug}.webp ada filenya (file
     * fisik sudah di-drop di situ), kembalikan URL LOKAL yang mengarah ke
     * route API CoverController. Kalau file belum ada, fallback ke
     * $originalUrl (URL cloud dari raw data asli).
     *
     * Beda dengan chapter images (banyak file per folder, perlu listing +
     * filter), cover cuma 1 file per series dengan nama tetap
     * "{slug}.webp" — jadi cukup dicek exists(), tidak perlu scan folder.
     */
    protected function resolveCoverUrl(string $slug, ?string $originalUrl): ?string
    {
        $disk = Storage::disk('local'); // root: storage/app/private
        $path = "series/cover/{$slug}.webp";

        if (! $disk->exists($path)) {
            $this->coversFromCloud++;

            return $originalUrl;
        }

        $this->coversFromLocal++;
        $baseUrl = rtrim(config('app.url'), '/');

        return "{$baseUrl}/api/v1/series/{$slug}/cover";
    }

    /**
     * Kalau storage/app/private/series/images/{slug}/{index}/ ada isinya
     * (file fisik sudah di-drop di situ), kembalikan URL LOKAL untuk tiap
     * file yang benar-benar ada — diurutkan alfabetis/numerik. File dengan
     * akhiran hash (mis. "001-CB6B073A9...jpg") diabaikan, itu sisa/gagal
     * proses; versi final-nya adalah file nomor polos ("001.jpg"). File
     * non-gambar (mis. ".tmp") juga diabaikan.
     *
     * File ini ada di disk PRIVAT (bukan public/), jadi tidak bisa pakai
     * asset() langsung — URL-nya mengarah ke route API
     * (App\Http\Controllers\Api\V1\ChapterImageController) yang men-stream
     * file dari Storage::disk('local') setelah dicek keberadaannya.
     *
     * Kalau folder belum ada/kosong, fallback ke $originalImages (URL
     * eksternal dari raw data asli).
     */
    protected function resolveChapterImages(string $slug, int $index, array $originalImages): array
    {
        $disk = Storage::disk('local'); // root: storage/app/private
        $dir = "series/images/{$slug}/{$index}";

        if (! $disk->exists($dir)) {
            $this->imagesFromCloud++;

            return $originalImages;
        }

        $files = collect($disk->files($dir))
            ->map(fn ($p) => basename($p))
            ->filter(fn ($name) => preg_match('/\.(jpe?g|png|webp|gif|avif)$/i', $name))
            ->reject(fn ($name) => preg_match('/-[0-9a-f]{16,}\.(jpe?g|png|webp|gif|avif)$/i', $name))
            ->sort(fn ($a, $b) => strnatcasecmp($a, $b))
            ->values();

        if ($files->isEmpty()) {
            $this->imagesFromCloud++;

            return $originalImages;
        }

        $this->imagesFromLocal++;
        $baseUrl = rtrim(config('app.url'), '/');

        return $files
            ->map(fn ($name) => "{$baseUrl}/api/v1/series/{$slug}/chapters/{$index}/images/{$name}")
            ->all();
    }

    protected function readJson(string $path): ?array
    {
        if (! File::exists($path)) {
            return null;
        }

        $decoded = json_decode(File::get($path), true);

        return json_last_error() === JSON_ERROR_NONE ? $decoded : null;
    }
}
