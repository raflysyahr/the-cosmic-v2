<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('genre_series', function (Blueprint $table) {
            $table->ulid('series_id');
            $table->ulid('genre_id');

            $table->foreign('series_id')->references('id')->on('series')->cascadeOnDelete();
            $table->foreign('genre_id')->references('id')->on('genres')->cascadeOnDelete();

            $table->primary(['series_id', 'genre_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('genre_series');
    }
};
