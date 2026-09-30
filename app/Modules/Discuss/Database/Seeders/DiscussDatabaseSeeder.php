<?php

namespace App\Modules\Discuss\Database\Seeders;

use Illuminate\Database\Seeder;

class DiscussDatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            DefaultRanksSeeder::class,
            DefaultEmotesSeeder::class,
        ]);
    }
}
