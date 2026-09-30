<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Series;
use Illuminate\Http\Request;

/**
 * Sumber data series/chapter sekarang tabel database (Series, Chapter,
 * ChapterPage, Genre) hasil SeriesDatabaseSeeder — BUKAN lagi live-proxy
 * ke KomikcastService. KomikcastService masih dipakai controller lain
 * (mis. Auth) untuk login/register ke server asli, jadi service itu
 * sendiri sengaja tidak dihapus.
 *
 * Kontrak response (success/data/meta, nama field tiap item) dijaga
 * identik dengan versi KomikcastService lama supaya frontend
 * (resources/js/api/series.ts, types/index.ts) tidak perlu diubah,
 * KECUALI field `id` & `genreIds` yang sekarang string (ULID) — frontend
 * sudah disesuaikan untuk itu.
 */
class SeriesController extends Controller
{
    public function index(Request $request)
    {
        $take = (int) $request->query('take', 20);
        $page = (int) $request->query('page', 1);
        $format = $request->query('format', '');
        $sort = $request->query('sort', '');
        // preset: belum ada logika khusus di sisi data (mis. "recommended"),
        // sama seperti versi mock-api/server.js sebelumnya — diterima tapi diabaikan.
        $genreId = $request->query('genreId');

        $query = Series::query()->with(['genres', 'latestChapter']);

        if ($format) {
            $query->where('type', $format);
        }

        if ($genreId) {
            $query->whereHas('genres', fn ($q) => $q->where('genres.id', $genreId));
        }

        match ($sort) {
            'rating' => $query->orderByDesc('rating'),
            'latest' => $query->orderByDesc('created_at'),
            default => $query->orderByDesc('created_at'),
        };

        $paginator = $query->paginate($take, ['*'], 'page', $page);

        return response()->json([
            'success' => true,
            'data' => $paginator->getCollection()->map(fn ($s) => $this->toSeriesItem($s))->all(),
            'meta' => [
                'total' => $paginator->total(),
                'page' => $paginator->currentPage(),
                'lastPage' => $paginator->lastPage(),
            ],
        ]);
    }

    public function search(Request $request)
    {
        $q = trim((string) $request->query('q', ''));
        $take = (int) $request->query('take', 12);
        $page = (int) $request->query('page', 1);

        if (! $q) {
            return response()->json([
                'success' => true,
                'data' => [],
                'meta' => ['total' => 0, 'page' => 1, 'lastPage' => 0],
            ]);
        }

        $query = Series::query()
            ->with(['genres', 'latestChapter'])
            ->where(fn ($w) => $w->where('title', 'like', "%{$q}%")
                ->orWhere('native_title', 'like', "%{$q}%"))
            ->orderByDesc('created_at');

        $paginator = $query->paginate($take, ['*'], 'page', $page);

        return response()->json([
            'success' => true,
            'data' => $paginator->getCollection()->map(fn ($s) => $this->toSeriesItem($s))->all(),
            'meta' => [
                'total' => $paginator->total(),
                'page' => $paginator->currentPage(),
                'lastPage' => $paginator->lastPage(),
            ],
        ]);
    }

    public function trending()
    {
        // Belum ada sinyal "trending" resmi tersimpan di database — sementara
        // dipakai `views` terbanyak (sama semangatnya dengan ranking di
        // mock-api/server.js sebelumnya, cuma metriknya beda krn yg tersedia
        // di skema sekarang adalah `views`).
        $series = Series::query()
            ->with(['genres', 'latestChapter'])
            ->orderByDesc('views')
            ->take(10)
            ->get();

        return response()->json([
            'success' => true,
            'data' => $series->map(fn ($s) => $this->toSeriesItem($s))->all(),
        ]);
    }

    public function show(string $slug)
    {
        $series = Series::query()
            ->with(['genres', 'chapters'])
            ->where('slug', $slug)
            ->first();

        if (! $series) {
            return response()->json(['success' => false, 'error' => 'Series not found'], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $this->toSeriesDetail($series),
        ]);
    }

    public function chapters(string $slug)
    {
        $series = Series::query()->where('slug', $slug)->first();

        if (! $series) {
            return response()->json(['success' => false, 'data' => []], 404);
        }

        $chapters = $series->chapters()->get();

        return response()->json([
            'success' => true,
            'data' => $chapters->map(fn ($c) => $this->toChapterItem($c))->all(),
        ]);
    }

    public function chapterPages(string $slug, int $index)
    {
        $series = Series::query()->where('slug', $slug)->first();

        if (! $series) {
            return response()->json(['success' => false, 'data' => ['images' => []]], 404);
        }

        $chapter = $series->chapters()->where('index', $index)->first();

        if (! $chapter) {
            return response()->json(['success' => false, 'data' => ['images' => []]], 404);
        }

        // pages di-load terpisah (relasi 1:1 ChapterPage) — baru dibuka
        // di sini, saat 1 chapter spesifik benar-benar diakses.
        $images = $chapter->pages?->images ?? [];

        return response()->json([
            'success' => true,
            'data' => ['images' => $images],
        ]);
    }

    protected function toSeriesItem(Series $s): array
    {
        $latest = $s->latestChapter;

        return [
            'id' => $s->id,
            'title' => $s->title,
            'slug' => $s->slug,
            'cover' => $s->cover,
            'rating' => (float) $s->rating,
            'status' => $s->status,
            'type' => $s->type,
            'isHot' => (bool) $s->is_hot,
            'totalChapters' => (int) $s->total_chapters,
            'genreIds' => $s->genres->pluck('id')->all(),
            'chapters' => [],
            'latestChapter' => $latest ? [
                'index' => $latest->index,
                'title' => $latest->title,
                'releasedAt' => optional($latest->released_at)->toIso8601String() ?? '',
            ] : null,
        ];
    }

    protected function toSeriesDetail(Series $s): array
    {
        $item = $this->toSeriesItem($s);

        return array_merge($item, [
            'nativeTitle' => $s->native_title ?? '',
            'author' => $s->author ?? '',
            'genres' => $s->genres->map(fn ($g) => [
                'id' => $g->id,
                'name' => $g->name,
                'slug' => $g->slug,
            ])->all(),
            'animeAdaptation' => (bool) $s->anime_adaptation,
            'synopsis' => $s->synopsis ?? '',
            'releasedAt' => optional($s->released_at)->toIso8601String() ?? '',
            'views' => (int) $s->views,
            'chapters' => $s->chapters->map(fn ($c) => $this->toChapterItem($c))->all(),
        ]);
    }

    protected function toChapterItem($c): array
    {
        return [
            'index' => $c->index,
            'title' => $c->title ?? "Chapter {$c->index}",
            'releasedAt' => optional($c->released_at)->toIso8601String() ?? '',
        ];
    }
}
