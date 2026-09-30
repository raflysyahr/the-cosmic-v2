<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Event multiplier yang diatur admin (mis. "Weekend x2").
        Schema::create('discuss_cp_events', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('name', 100);
            // 200 = x2, 150 = x1.5. Integer supaya tidak ada float drift.
            $table->unsignedSmallInteger('multiplier_pct');
            // Daftar source yang ikut digandakan; null = semua source.
            $table->json('sources')->nullable();
            // Batasi ke satu room; null = semua room.
            $table->ulid('room_id')->nullable();
            $table->timestamp('starts_at');
            $table->timestamp('ends_at');
            $table->boolean('is_active')->default(true);
            $table->ulid('created_by')->nullable();
            $table->timestamps();

            $table->index(['is_active', 'starts_at', 'ends_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('discuss_cp_events');
    }
};
