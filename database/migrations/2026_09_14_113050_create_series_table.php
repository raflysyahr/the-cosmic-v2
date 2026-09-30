<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('series', function (Blueprint $table) {
            $table->ulid('id')->primary();

            // Dipakai frontend (ComicCard, Detail)
            $table->string('slug', 191)->unique();
            $table->string('title', 255);
            $table->string('native_title', 255)->nullable();
            $table->text('cover')->nullable();
            $table->decimal('rating', 3, 1)->default(0);
            $table->string('status', 20)->default('ongoing'); // ongoing | completed
            $table->string('type', 20)->nullable();            // manhwa | manhua | manga | webtoon
            $table->boolean('is_hot')->default(false);
            $table->unsignedInteger('total_chapters')->default(0);
            $table->string('author', 255)->nullable();
            $table->boolean('anime_adaptation')->default(false);
            $table->text('synopsis')->nullable();
            $table->unsignedBigInteger('views')->default(0);
            $table->timestamp('released_at')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('series');
    }
};
