<?php

namespace App\Modules\Cultivation\Services;

use App\Modules\Auth\Models\User;
use App\Modules\Cultivation\Models\CultivationEra;
use App\Modules\Cultivation\Models\CultivationProgressLog;
use App\Modules\Cultivation\Models\CultivationRealm;
use App\Modules\Cultivation\Models\UserCultivation;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;


/**
 * Logika bisnis inti Cosmic Cultivation System. Lihat dokumentasi ERD
 * yang menyertai fitur ini untuk penjelasan formula & struktur lengkap.
 *
 * Titik masuk utama: addProgress(). Semua sumber XP (baca chapter,
 * komentar, mission board, atau sumber lain di masa depan) memanggil
 * method yang sama ini dengan $source berbeda — service ini yang
 * mengurus breakthrough antar-stage/realm/era secara otomatis.
 *
 * Konvensi AGENTS.md §2: TIDAK ADA Eloquent relationship sama sekali di
 * modul ini (bahkan sesama model dalam modul yang sama) — semua akses
 * ke tabel lain lewat query manual select()/where() di service ini,
 * bukan lewat method relasi di Model.
 *
 * Contoh pemakaian untuk modul yang BELUM dibangun (Comment, Mission):
 *
 *   // Saat modul Comment dibuat, di titik user berhasil post comment:
 *   app(CultivationService::class)->addProgress(
 *       $user,
 *       'comment',
 *       "comment:{$comment->id}",              // reference unik per comment
 *       config('cultivation.xp.comment'),
 *   );
 *
 *   // Saat modul Mission Board dibuat, di titik user klaim reward mission:
 *   app(CultivationService::class)->addProgress(
 *       $user,
 *       'mission',
 *       "mission:{$mission->id}:{$user->id}",  // reference unik per klaim
 *       $mission->reward_amount ?? config('cultivation.xp.mission_default'),
 *   );
 *
 * $reference HARUS unik per kejadian yang boleh dihitung sekali — dedupe
 * otomatis lewat cultivation_progress_logs (lihat penjelasan di bawah).
 */
class CultivationService
{
    /**
     * Ambil (atau buat kalau belum ada) progress cultivation user.
     * User baru otomatis mulai di realm pertama (sort_order=1), stage 1,
     * progress 0 — tidak perlu seeding manual per-user.
     */
    public function getOrCreateForUser(User $user): UserCultivation
    {
        $existing = UserCultivation::where('user_id', $user->id)->first();
        if ($existing) {
            return $existing;
        }

        $firstRealm = CultivationRealm::select('id')
            ->where('sort_order', 1)
            ->firstOrFail();

        return UserCultivation::create([
            'user_id' => $user->id,
            'realm_id' => $firstRealm->id,
            'stage' => 1,
            'progress' => 0,
        ]);
    }

    /**
     * Tambah progress cultivation user dari sumber apa pun (chapter_read,
     * comment, mission, dll), lalu proses breakthrough stage/realm/era
     * kalau progress sudah cukup — bisa breakthrough berkali-kali sekaligus
     * kalau $amount besar (mis. reward mission gede).
     *
     * $reference dipakai untuk dedupe (lihat cultivation_progress_logs):
     * kombinasi (user, source, reference) yang sama HANYA diproses SEKALI.
     * Ini mencegah spam — mis. baca ulang chapter yang sama tidak akan
     * kasih XP dobel. Kalau referensi sudah pernah dipakai, method ini
     * return null tanpa efek apa pun (bukan exception, supaya caller —
     * mis. listener baca chapter — tidak perlu menangani ini secara
     * khusus, cukup panggil terus setiap kali event terjadi).
     *
     * @return array{realm: CultivationRealm, era: CultivationEra, stage: int, progress: int, breakthroughs: array<int, array{from_realm: string, to_realm: string, to_stage: int}>}|null
     */
    public function addProgress(User $user, string $source, string $reference, int $amount): ?array
    {
        if ($amount <= 0) {
            return null;
        }

        return DB::transaction(function () use ($user, $source, $reference, $amount) {
            // Dedupe: kombinasi user+source+reference cuma boleh sekali.
            // Kalau insert gagal karena unique constraint, berarti sumber
            // ini sudah pernah dipakai sebelumnya.
            try {
                CultivationProgressLog::create([
                    'user_id' => $user->id,
                    'source' => $source,
                    'reference' => $reference,
                    'amount' => $amount,
                ]);
            } catch (QueryException $e) {
                if (str_contains($e->getMessage(), 'uq_cultivation_log_dedupe')) {
                    return null;
                }
                throw $e;
            }

            $cultivation = $this->getOrCreateForUser($user);
            $progress = $cultivation->progress + $amount;
            $stage = $cultivation->stage;
            $realm = CultivationRealm::where('id', $cultivation->realm_id)->firstOrFail();

            $breakthroughs = [];
            while (true) {
                $stageRequired = $realm->stage_required;

                if ($progress < $stageRequired) {
                    break;
                }

                $progress -= $stageRequired;
                $fromRealmName = $realm->full_name;

                if ($stage < 10) {
                    $stage++;
                } else {
                    $nextRealm = CultivationRealm::where('sort_order', $realm->sort_order + 1)->first();

                    if (! $nextRealm) {
                        // Realm 20 stage 10 sudah maksimum — sisa progress
                        // di atas requirement dibuang (capped).
                        $progress = 0;
                        break;
                    }

                    $realm = $nextRealm;
                    $stage = 1;
                }

                $breakthroughs[] = [
                    'from_realm' => $fromRealmName,
                    'to_realm' => $realm->full_name,
                    'to_stage' => $stage,
                ];
            }

            $cultivation->update([
                'realm_id' => $realm->id,
                'stage' => $stage,
                'progress' => $progress,
            ]);

            $era = CultivationEra::where('id', $realm->era_id)->firstOrFail();

            return [
                'realm' => $realm,
                'era' => $era,
                'stage' => $stage,
                'progress' => $progress,
                'breakthroughs' => $breakthroughs,
            ];
        });
    }

    /**
     * Ringkasan status cultivation user untuk ditampilkan di UI —
     * level, realm, era, resource, progress, dan persentase.
     */
    public function getStatus(User $user): array
    {
        $cultivation = $this->getOrCreateForUser($user);
        $realm = CultivationRealm::where('id', $cultivation->realm_id)->firstOrFail();
        $era = CultivationEra::where('id', $realm->era_id)->firstOrFail();

        return [
            'level' => $realm->level_start + $cultivation->stage - 1,
            'stage' => $cultivation->stage,
            'progress' => $cultivation->progress,
            'stage_required' => $realm->stage_required,
            'progress_percent' => round(($cultivation->progress / $realm->stage_required) * 100, 2),
            'realm' => [
                'id' => $realm->id,
                'name' => $realm->name,
                'full_name' => $realm->full_name,
                'sort_order' => $realm->sort_order,
            ],
            'era' => [
                'id' => $era->id,
                'name' => $era->name,
                'resource_name' => $era->resource_name,
                'resource_slug' => $era->resource_slug,
            ],
            'is_max_level' => $realm->sort_order === 20 && $cultivation->stage === 10,
            'badge' => $this->getRealmBadgeData([$user->id])[$user->id] ?? null,
        ];
    }

    /**
     * Ringkasan realm untuk badge (dipakai MessageResource Discuss) —
     * versi ringan dari getStatus(), field yang perlu ditampilkan di
     * badge chat & modal detail Crest: nama realm, slug ikon, level
     * saat ini, description lore realm, era_name (nama era penuh, mis.
     * "Mortal Era"), dan source_name (resource energi era, mis.
     * "Essence"/"Astrum").
     * Dipanggil per-user secara batch oleh caller (lihat
     * MessageResource::toArray) untuk hindari N+1 query.
     */
    public function getRealmBadgeData(array $userIds): array
    {
        if (empty($userIds)) {
            return [];
        }

        $cultivations = UserCultivation::whereIn('user_id', $userIds)->get()->keyBy('user_id');
        if ($cultivations->isEmpty()) {
            return [];
        }

        $realmIds = $cultivations->pluck('realm_id')->unique()->values()->all();
        $realms = CultivationRealm::whereIn('id', $realmIds)->get()->keyBy('id');

        $eraIds = $realms->pluck('era_id')->unique()->values()->all();
        $eras = CultivationEra::whereIn('id', $eraIds)->get()->keyBy('id');

        $badges = [];
        foreach ($cultivations as $userId => $cultivation) {
            $realm = $realms->get($cultivation->realm_id);
            if (! $realm) continue;

            $era = $eras->get($realm->era_id);

            $badges[$userId] = [
                'realm_name' => $realm->name,
                'realm_slug' => $this->realmIconSlug($realm->name),
                'description' => $realm->description,
                'era_name' => $era?->name,
                'source_name' => $era?->resource_name,
                'stage' => $cultivation->stage,
                'level' => $realm->level_start + $cultivation->stage - 1,
            ];
        }

        return $badges;
    }

    /**
     * Slug KHUSUS untuk nama file ikon realm di
     * storage/app/private/realm/{slug}.png — BUKAN Str::slug() biasa.
     *
     * Str::slug('Star Lord') menghasilkan "star-lord" (pakai dash), tapi
     * nama file ikon asli adalah "starlord.png" (tanpa dash) — begitu
     * juga "World Lord" -> "worldlord.png". Realm dengan nama satu kata
     * (mis. "Awakened", "Cosmic") tidak terpengaruh perbedaan ini.
     * Kalau nanti ada realm baru bernama 2 kata lagi, pastikan file
     * ikonnya juga tanpa dash, konsisten dengan pola ini.
     */
    private function realmIconSlug(string $name): string
    {
        return strtolower(str_replace(' ', '', $name));
    }

    /**
     * Data referensi LENGKAP semua Era + Realm, dipakai halaman panduan
     * publik (bukan status personal user — lihat getStatus() untuk itu).
     * Tidak butuh $user sama sekali, murni data statis dari tabel
     * referensi cultivation_eras/cultivation_realms.
     *
     * @return array<int, array{id: string, name: string, resource_name: string, resource_slug: string, realms: array<int, array{id: string, name: string, full_name: string, slug: string, level_start: int, level_end: int, stage_required: int, sort_order: int}>}>
     */
    public function getGuideData(): array
    {
        $eras = CultivationEra::orderBy('sort_order')->get();
        $realms = CultivationRealm::orderBy('sort_order')->get()->groupBy('era_id');

        return $eras->map(function (CultivationEra $era) use ($realms) {
            $eraRealms = $realms->get($era->id, collect());

            return [
                'id' => $era->id,
                'name' => $era->name,
                'resource_name' => $era->resource_name,
                'resource_slug' => $era->resource_slug,
                'realms' => $eraRealms->map(fn (CultivationRealm $realm) => [
                    'id' => $realm->id,
                    'name' => $realm->name,
                    'full_name' => $realm->full_name,
                    'slug' => $this->realmIconSlug($realm->name),
                    'level_start' => $realm->level_start,
                    'level_end' => $realm->level_end,
                    'stage_required' => $realm->stage_required,
                    'sort_order' => $realm->sort_order,
                ])->values()->all(),
            ];
        })->values()->all();
    }
}
