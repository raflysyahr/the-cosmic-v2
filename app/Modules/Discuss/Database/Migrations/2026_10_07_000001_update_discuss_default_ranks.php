<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Ganti rank global bawaan (Newcomer/Regular/Veteran/Legend) menjadi 12 level
 * Outer Disciple → Celestial Disciple, lalu hitung ulang rank semua member.
 *
 * - Hanya berjalan bila rank global masih memakai nama bawaan lama. Instalasi
 *   baru (tabel kosong, rank dibuat DefaultRanksSeeder) dan rank yang sudah
 *   dikustom admin tidak disentuh.
 * - Baris rank diperbarui di tempat, jadi `discuss_members.rank_id` tetap valid.
 * - Rank member dihitung ulang tanpa memicu event MemberRankUpgraded (senyap).
 *
 * Data sengaja ditulis ulang di sini (snapshot), bukan memanggil seeder, supaya
 * migrasi ini tidak berubah perilakunya kalau seeder diubah di kemudian hari.
 */
return new class extends Migration
{
    /** [order, name, label_color, min_xp] */
    private array $new = [
        [1,  'Outer Disciple',     '#6b7280', 0],
        [2,  'Inner Disciple',     '#22c55e', 500],
        [3,  'Core Disciple',      '#14b8a6', 1500],
        [4,  'True Disciple',      '#3b82f6', 3000],
        [5,  'Elite Disciple',     '#6366f1', 5500],
        [6,  'Elder Disciple',     '#8b5cf6', 9000],
        [7,  'Heavenly Disciple',  '#d946ef', 14000],
        [8,  'Saint Disciple',     '#ec4899', 21000],
        [9,  'Immortal Disciple',  '#f97316', 30000],
        [10, 'Ancient Disciple',   '#ef4444', 42000],
        [11, 'Eternal Disciple',   '#f59e0b', 58000],
        [12, 'Celestial Disciple', '#facc15', 80000],
    ];

    /** [order, name, label_color, min_xp] */
    private array $old = [
        [1, 'Newcomer', '#6b7280', 0],
        [2, 'Regular',  '#22c55e', 100],
        [3, 'Veteran',  '#3b82f6', 500],
        [4, 'Legend',   '#f59e0b', 2000],
    ];

    public function up(): void
    {
        $this->apply($this->new, array_column($this->old, 1));
    }

    public function down(): void
    {
        $this->apply($this->old, array_column($this->new, 1));

        // Level 5-12 tidak ada di set lama: hapus yang masih bernama bawaan baru.
        $extraNames = array_column(array_slice($this->new, count($this->old)), 1);
        DB::table('discuss_ranks')->whereNull('room_id')->whereIn('name', $extraNames)->delete();

        $this->syncMemberRanks();
    }

    /**
     * @param  list<array{0:int,1:string,2:string,3:int}>  $target
     * @param  list<string>  $recognizedNames  nama yang menandakan rank global masih bawaan
     */
    private function apply(array $target, array $recognizedNames): void
    {
        $globals = DB::table('discuss_ranks')->whereNull('room_id')->get();

        if (! $globals->contains(fn ($rank) => in_array($rank->name, $recognizedNames, true))) {
            return;
        }

        foreach ($target as [$order, $name, $color, $minXp]) {
            $values = ['name' => $name, 'label_color' => $color, 'min_xp' => $minXp];
            $existing = $globals->where('order', $order);

            if ($existing->isEmpty()) {
                DB::table('discuss_ranks')->insert($values + [
                    'id'         => (string) Str::ulid(),
                    'room_id'    => null,
                    'order'      => $order,
                    'perks'      => json_encode([]),
                    'created_at' => now(),
                ]);
            } else {
                DB::table('discuss_ranks')->whereIn('id', $existing->pluck('id'))->update($values);
            }
        }

        $this->syncMemberRanks();
    }

    /**
     * Pilih rank tertinggi yang min_xp-nya terpenuhi — aturan yang sama dengan
     * RankService::checkPromotion() (rank global + rank khusus room).
     */
    private function syncMemberRanks(): void
    {
        $global = DB::table('discuss_ranks')->whereNull('room_id')->get();
        $byRoom = [];

        $eligibleFor = function (string $roomId, int $xp) use ($global, &$byRoom) {
            $byRoom[$roomId] ??= $global
                ->concat(DB::table('discuss_ranks')->where('room_id', $roomId)->get())
                ->sortByDesc('order')
                ->values();

            return $byRoom[$roomId]->first(fn ($rank) => (int) $rank->min_xp <= $xp);
        };

        DB::table('discuss_members')
            ->where(fn ($q) => $q->whereNotNull('rank_id')->orWhere('xp_points', '>', 0))
            ->chunkById(500, function ($members) use ($eligibleFor) {
                foreach ($members as $member) {
                    $rank = $eligibleFor((string) $member->room_id, (int) $member->xp_points);

                    if ($rank && $rank->id !== $member->rank_id) {
                        DB::table('discuss_members')->where('id', $member->id)->update(['rank_id' => $rank->id]);
                    }
                }
            });
    }
};
