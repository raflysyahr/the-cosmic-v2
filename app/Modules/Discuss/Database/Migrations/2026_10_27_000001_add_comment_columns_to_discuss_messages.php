<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Komentar di halaman baca komik = pesan biasa di grup diskusi komik yang SAMA
        // (room `comic-{slug}`), ditandai chapter-nya. Kolom terstruktur (bukan metadata JSON)
        // karena difilter lewat WHERE di setiap pemuatan komentar, dan karena `metadata` kiriman
        // klien tidak boleh bisa menandai pesan sebagai komentar. Kolom ini hanya diisi server
        // (CommentService), tidak pernah dari request.
        Schema::table('discuss_messages', function (Blueprint $table) {
            $table->string('comment_series_slug', 191)->nullable()->after('metadata');
            $table->unsignedInteger('comment_chapter_index')->nullable()->after('comment_series_slug');

            $table->index(
                ['room_id', 'comment_series_slug', 'comment_chapter_index', 'created_at'],
                'discuss_messages_comment_lookup',
            );
        });
    }

    public function down(): void
    {
        Schema::table('discuss_messages', function (Blueprint $table) {
            $table->dropIndex('discuss_messages_comment_lookup');
            $table->dropColumn(['comment_series_slug', 'comment_chapter_index']);
        });
    }
};
