<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cultivation_stages', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('realm_id')->constrained('cultivation_realms')->cascadeOnDelete();

            $table->unsignedInteger('stage'); // 1-10 dalam realm-nya
            $table->unsignedInteger('level'); // 1-200 global, dihitung dari realm.level_start + stage - 1

            $table->unsignedBigInteger('start_progress')->default(0);
            $table->unsignedBigInteger('end_progress'); // = realm.stage_required

            $table->timestamps();

            $table->unique(['realm_id', 'stage']);
            $table->unique('level');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cultivation_stages');
    }
};
