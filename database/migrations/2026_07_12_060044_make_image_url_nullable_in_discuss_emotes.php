<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('discuss_emotes', function (Blueprint $table) {
            $table->text('image_url')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('discuss_emotes', function (Blueprint $table) {
            $table->text('image_url')->nullable(false)->change();
        });
    }
};
