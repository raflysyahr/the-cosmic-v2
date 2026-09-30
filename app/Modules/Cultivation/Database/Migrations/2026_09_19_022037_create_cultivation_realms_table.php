<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cultivation_realms', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('era_id')->constrained('cultivation_eras')->cascadeOnDelete();

            $table->string('name', 100);
            $table->string('full_name', 150);

            $table->unsignedInteger('level_start');
            $table->unsignedInteger('level_end');

            $table->unsignedBigInteger('stage_required');
            $table->unsignedBigInteger('realm_total_required');

            $table->unsignedInteger('sort_order');
            $table->timestamps();

            $table->unique('sort_order');
            $table->unique(['era_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cultivation_realms');
    }
};
