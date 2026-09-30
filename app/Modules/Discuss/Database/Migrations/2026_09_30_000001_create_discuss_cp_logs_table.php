<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Ledger CP: setiap poin yang masuk/keluar tercatat di sini. Saldo
        // per-room tetap di discuss_members.xp_points (dipakai rank);
        // leaderboard mingguan/bulanan dihitung dari SUM(amount) di tabel ini.
        Schema::create('discuss_cp_logs', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('user_id');
            $table->ulid('room_id')->nullable();
            $table->string('source', 30);
            // Kunci dedupe: 1 sumber unik (mis. 1 pesan, 1 reaktor pada 1
            // pesan) hanya boleh memberi CP sekali per user.
            $table->string('reference', 120);
            // Pesan yang menjadi sebab CP (untuk batas per-pesan & pencabutan).
            $table->ulid('subject_id')->nullable();
            // Poin dasar sebelum multiplier; amount = poin yang benar-benar
            // masuk (negatif untuk entri pencabutan).
            $table->integer('base_amount')->default(0);
            $table->unsignedSmallInteger('multiplier_pct')->default(100);
            $table->integer('amount');
            $table->ulid('event_id')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->unique(['user_id', 'source', 'reference']);
            $table->index(['user_id', 'created_at']);
            $table->index(['source', 'created_at']);
            $table->index('subject_id');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('discuss_cp_logs');
    }
};
