<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Satu foto ATAU video per post. `body` dipakai sebagai caption; untuk post
        // bermedia, title/body boleh kosong (disimpan sebagai string kosong, kolomnya
        // tetap NOT NULL seperti sebelumnya).
        Schema::table('discuss_announcements', function (Blueprint $table) {
            $table->string('media_type', 10)->nullable()->after('link_url');   // image | video
            $table->text('media_url')->nullable()->after('media_type');
            $table->text('media_thumbnail')->nullable()->after('media_url');   // poster video
            $table->json('media_meta')->nullable()->after('media_thumbnail');  // duration, width, height, size
        });

        // Satu reaksi per user per post (bisa diganti / dicabut).
        Schema::create('discuss_announcement_reactions', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('announcement_id');
            $table->ulid('user_id');
            $table->string('emoji', 16);
            $table->timestamp('created_at')->nullable();

            $table->unique(['announcement_id', 'user_id']);
            $table->index(['announcement_id', 'emoji']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('discuss_announcement_reactions');

        Schema::table('discuss_announcements', function (Blueprint $table) {
            $table->dropColumn(['media_type', 'media_url', 'media_thumbnail', 'media_meta']);
        });
    }
};
