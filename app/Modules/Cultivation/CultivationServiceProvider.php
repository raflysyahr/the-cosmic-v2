<?php

namespace App\Modules\Cultivation;

use Illuminate\Support\ServiceProvider;

class CultivationServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/Database/Migrations');
        $this->loadRoutesFrom(__DIR__ . '/routes/cultivation.php');
    }
}
