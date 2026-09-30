<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cultivation_progress_logs', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('user_id')->constrained('users')->cascadeOnDelete();

            // 'chapter_read' | 'comment' | 'mission' | dst — sumber pemberi XP
            $table->string('source', 50);

            // Referensi unik ke hal yang memicu XP, dipakai untuk cegah
            // duplikasi/spam — mis. "series-slug:chapter-index" untuk
            // chapter_read, "comment:{id}" untuk comment, "mission:{id}"
            // untuk mission. Kombinasi (user_id, source, reference) unik,
            // jadi 1 sumber yang sama cuma bisa kasih XP SEKALI per user.
            $table->string('reference', 191);

            $table->unsignedBigInteger('amount');
            $table->timestamps();

            $table->unique(['user_id', 'source', 'reference'], 'uq_cultivation_log_dedupe');
            $table->index(['user_id', 'source']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cultivation_progress_logs');
    }
};
