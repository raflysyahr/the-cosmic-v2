<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chapters', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('series_id');

            // Dipakai frontend (Detail chapter list, Reader)
            $table->unsignedInteger('index'); // urutan chapter, index 0 = prolog
            $table->string('title', 255)->nullable();
            $table->json('images')->nullable(); // array URL halaman, dipakai Reader
            $table->timestamp('released_at')->nullable();

            $table->timestamps();

            $table->foreign('series_id')->references('id')->on('series')->cascadeOnDelete();
            $table->unique(['series_id', 'index']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chapters');
    }
};
