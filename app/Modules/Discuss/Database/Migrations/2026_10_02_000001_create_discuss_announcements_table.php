<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Pemberitahuan dari admin platform, ditampilkan di halaman Story.
        // User tidak membuat story — hanya admin yang menulis, user membaca.
        Schema::create('discuss_announcements', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('title', 120);
            $table->text('body');
            $table->string('link_url', 500)->nullable();
            $table->boolean('is_pinned')->default(false);
            // Jadwal tayang. Tanggal di masa depan = terjadwal, belum terlihat user.
            $table->timestamp('published_at')->nullable();
            $table->ulid('created_by')->nullable();
            $table->timestamps();

            $table->index('published_at');
            $table->index(['is_pinned', 'published_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('discuss_announcements');
    }
};
