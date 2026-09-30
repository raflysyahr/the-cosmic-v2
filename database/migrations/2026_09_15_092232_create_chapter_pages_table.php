<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chapter_pages', function (Blueprint $table) {
            // Dipakai sebagai primary key SEKALIGUS foreign key -> relasi
            // 1:1 murni dengan chapters (tidak butuh kolom id terpisah).
            $table->ulid('chapter_id')->primary();
            $table->json('images')->nullable(); // array URL halaman, cuma dibuka pas Reader akses 1 chapter

            $table->timestamps();

            $table->foreign('chapter_id')->references('id')->on('chapters')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chapter_pages');
    }
};
