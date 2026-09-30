<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reading_histories', function (Blueprint $table) {
            $table->text('cover_image')->nullable()->change();
            $table->text('chapter_url')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('reading_histories', function (Blueprint $table) {
            $table->string('cover_image', 255)->nullable()->change();
            $table->string('chapter_url', 255)->nullable()->change();
        });
    }
};
