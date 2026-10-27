<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Reaksi untuk HALAMAN chapter (bukan untuk komentar). Terpisah dari tabel reaksi Discuss.
        // Kunci komik memakai slug + index chapter, sama seperti bookmarks/reading_histories;
        // `user_id` ULID tanpa FK (konvensi project).
        Schema::create('chapter_reactions', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('series_slug', 191);
            $table->unsignedInteger('chapter_index');
            $table->ulid('user_id');
            // Kunci ASCII (like, love, laugh, wow, sad, fire) — BUKAN emoji mentah. Emoji non-BMP
            // dianggap sama oleh collation utf8mb4_unicode_ci di MySQL/MariaDB sehingga hitungan
            // per-emoji tercampur (pelajaran dari reaksi Story). Emoji hanya ada di frontend.
            $table->string('reaction', 16);
            $table->timestamp('created_at')->nullable();

            // Satu reaksi per user per chapter (bisa diganti / dicabut).
            $table->unique(['series_slug', 'chapter_index', 'user_id'], 'chapter_reactions_one_per_user');
            $table->index(['series_slug', 'chapter_index', 'reaction'], 'chapter_reactions_count_lookup');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chapter_reactions');
    }
};
