<?php

namespace App\Modules\Discuss\Database\Seeders;

use App\Modules\Discuss\Models\Rank;
use Illuminate\Database\Seeder;

/**
 * Rank global bawaan (room_id = null). Idempoten: dijalankan ulang akan
 * memperbarui baris pada `order` yang sama, bukan menggandakannya.
 *
 * Perubahan angka/nama di sini perlu migrasi data untuk database yang sudah
 * berjalan (lihat 2026_10_07_000001_update_discuss_default_ranks.php).
 */
class DefaultRanksSeeder extends Seeder
{
    /** [order, name, label_color, min_xp] */
    public const RANKS = [
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

    public function run(): void
    {
        foreach (self::RANKS as [$order, $name, $color, $minXp]) {
            $attributes = [
                'name'        => $name,
                'label_color' => $color,
                'min_xp'      => $minXp,
                'order'       => $order,
                'perks'       => [],
            ];

            $existing = Rank::global()->where('order', $order)->first();

            $existing ? $existing->update($attributes) : Rank::create($attributes);
        }
    }
}
