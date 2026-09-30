<?php

namespace App\Modules\Cultivation\Http\Controllers;

use App\Modules\Cultivation\Services\CultivationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class CultivationController
{
    public function __construct(
        private readonly CultivationService $cultivationService,
    ) {}

    public function show(Request $request): JsonResponse
    {
        $status = $this->cultivationService->getStatus($request->user());

        return response()->json(['success' => true, 'data' => $status]);
    }

    /**
     * Data referensi (5 era + 20 realm) untuk halaman panduan publik
     * (Pages/CultivationGuide.tsx) — TIDAK butuh auth, ini bukan data
     * personal user, murni tabel referensi statis.
     */
    public function guide(): JsonResponse
    {
        $eras = $this->cultivationService->getGuideData();

        return response()->json(['success' => true, 'data' => $eras]);
    }

    /**
     * Beri XP "chapter selesai dibaca" — TERPISAH dari
     * ReadingHistoryService::markRead() (yang cuma catat riwayat baca,
     * dipakai fitur "continue reading" dan tetap terpicu instan begitu
     * chapter dibuka). Endpoint ini baru dipanggil frontend
     * (Reader.tsx) setelah SEMUA syarat anti-curang terpenuhi di sisi
     * client: semua gambar ter-load, scroll sampai mentok bawah, dan
     * total waktu baca > 6 detik.
     *
     * Client TIDAK bisa dipercaya sepenuhnya (bisa saja seseorang
     * panggil endpoint ini langsung lewat Postman tanpa benar-benar
     * baca), jadi validasi waktu DIULANG di server: $startedAt yang
     * dikirim client dicocokkan ke waktu sekarang di server. Ini tidak
     * membuktikan gambar benar-benar ter-load (itu murni state client),
     * tapi setidaknya memastikan selisih waktu klaim tidak bisa
     * dipalsukan dengan mengirim timestamp masa lalu — server pakai
     * jamnya sendiri, bukan percaya penuh ke durasi yang dihitung client.
     */
    public function completeChapter(Request $request): JsonResponse
    {
        $request->validate([
            'slug' => ['required', 'string', 'max:191'],
            'chapter_index' => ['required', 'integer', 'min:0'],
            'started_at' => ['required', 'date'],
        ]);

        $startedAt = Carbon::parse($request->input('started_at'));
        $elapsedSeconds = $startedAt->diffInSeconds(now());

        // Batas bawah: sesuai syarat produk (>6 detik). Batas atas
        // longgar (1 jam) cuma jaga-jaga dari $startedAt yang error/basi
        // (mis. tab dibiarkan terbuka semalaman) — bukan bagian dari
        // syarat anti-curang, murni sanity check.
        if ($elapsedSeconds < 6 || $elapsedSeconds > 3600) {
            return response()->json([
                'success' => false,
                'error' => 'Reading duration requirement not met.',
            ], 422);
        }

        $slug = $request->input('slug');
        $chapterIndex = $request->input('chapter_index');
        $amount = config('cultivation.xp.chapter_read');

        $result = $this->cultivationService->addProgress(
            $request->user(),
            'chapter_read',
            "{$slug}:{$chapterIndex}",
            $amount,
        );

        // $result null berarti sudah pernah diklaim sebelumnya (dedupe
        // di cultivation_progress_logs) — bukan error, cuma tidak ada
        // gain baru untuk ditampilkan.
        if (! $result) {
            return response()->json(['success' => true, 'cultivation_gain' => null]);
        }

        return response()->json([
            'success' => true,
            'cultivation_gain' => [
                'amount' => $amount,
                'resource_name' => $result['era']->resource_name,
            ],
        ]);
    }
}
