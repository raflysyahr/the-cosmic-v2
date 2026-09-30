<?php

namespace Database\Seeders;

use App\Modules\Auth\Database\Seeders\AuthDatabaseSeeder;
use App\Modules\Cultivation\Database\Seeders\CultivationDatabaseSeeder;
use App\Modules\Discuss\Database\Seeders\DiscussDatabaseSeeder;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            AuthDatabaseSeeder::class,
            DiscussDatabaseSeeder::class,
            SeriesDatabaseSeeder::class,
            CultivationDatabaseSeeder::class,
        ]);
    }
}
