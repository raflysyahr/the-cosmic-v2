<?php

namespace App\Modules\Discuss\Database\Seeders;

use App\Modules\Discuss\Models\Rank;
use Illuminate\Database\Seeder;

class DefaultRanksSeeder extends Seeder
{
    public function run(): void
    {
        $ranks = [
            ['name' => 'Newcomer', 'label_color' => '#6b7280', 'min_xp' => 0, 'order' => 1, 'perks' => []],
            ['name' => 'Regular', 'label_color' => '#22c55e', 'min_xp' => 100, 'order' => 2, 'perks' => []],
            ['name' => 'Veteran', 'label_color' => '#3b82f6', 'min_xp' => 500, 'order' => 3, 'perks' => []],
            ['name' => 'Legend', 'label_color' => '#f59e0b', 'min_xp' => 2000, 'order' => 4, 'perks' => []],
        ];

        foreach ($ranks as $rank) {
            Rank::create($rank);
        }
    }
}
