<?php

namespace App\Modules\Discuss\Http\Controllers;

use App\Modules\Cultivation\Models\CultivationEra;
use App\Modules\Discuss\Enums\CpSource;
use App\Modules\Discuss\Events\ContributionAwarded;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

/**
 * Halaman demo toast — hanya untuk lingkungan lokal. Rutenya sendiri hanya
 * didaftarkan jika APP_ENV=local (routes/web.php); pengecekan di sini
 * adalah lapis kedua kalau rute itu suatu saat ikut terdaftar.
 *
 * Tidak menulis apa pun ke database: tidak ada ledger CP, tidak ada
 * progres Cultivation. Yang diuji murni jalur tampilan dan jalur push
 * Reverb → Echo → toast.
 */
class ToastDemoController
{
    public function page()
    {
        $this->ensureLocal();

        return Inertia::render('Dev/ToastDemo', [
            // Nama resource era asli (mis. "Essence") supaya demo mirip kenyataan.
            'resourceName' => CultivationEra::query()->value('resource_name') ?: 'Essence',
        ]);
    }

    /** Kirim ContributionAwarded sungguhan (lewat broadcaster) ke user yang sedang login. */
    public function push(Request $request): JsonResponse
    {
        $this->ensureLocal();

        $data = $request->validate([
            'source' => ['required', 'string', Rule::in([...CpSource::earnable(), CpSource::Achievement->value])],
            'amount' => ['required', 'integer', 'min:1', 'max:1000'],
            'multiplier' => ['sometimes', 'numeric', 'min:1', 'max:10'],
            'event_name' => ['sometimes', 'nullable', 'string', 'max:60'],
            'detail' => ['sometimes', 'nullable', 'string', 'max:60'],
        ]);

        $multiplier = (float) ($data['multiplier'] ?? 1);

        event(new ContributionAwarded(
            userId: (string) $request->user()->id,
            roomId: null,
            source: $data['source'],
            amount: (int) $data['amount'],
            baseAmount: (int) round($data['amount'] / $multiplier),
            multiplierPct: (int) round($multiplier * 100),
            eventName: $data['event_name'] ?? null,
            detail: $data['detail'] ?? null,
        ));

        return response()->json(['sent' => true]);
    }

    private function ensureLocal(): void
    {
        abort_unless(app()->environment('local'), 404);
    }
}
