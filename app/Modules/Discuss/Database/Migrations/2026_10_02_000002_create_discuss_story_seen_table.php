<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Satu baris per user: kapan terakhir membuka halaman Story.
        // Pemberitahuan dengan published_at setelah waktu ini dianggap belum dibaca.
        Schema::create('discuss_story_seen', function (Blueprint $table) {
            $table->ulid('user_id')->primary();
            $table->timestamp('seen_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('discuss_story_seen');
    }
};
