<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reading_histories', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('user_id');
            $table->string('slug');
            $table->string('title');
            $table->string('cover_image')->nullable();
            $table->integer('chapter_index');
            $table->string('chapter_title')->nullable();
            $table->string('chapter_url')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'slug', 'chapter_index']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reading_histories');
    }
};
