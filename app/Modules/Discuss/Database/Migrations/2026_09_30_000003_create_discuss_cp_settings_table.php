<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Override admin atas config/discuss_cp.php. Satu baris per key.
        Schema::create('discuss_cp_settings', function (Blueprint $table) {
            $table->string('key', 60)->primary();
            $table->json('value');
            $table->ulid('updated_by')->nullable();
            $table->timestamp('updated_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('discuss_cp_settings');
    }
};
