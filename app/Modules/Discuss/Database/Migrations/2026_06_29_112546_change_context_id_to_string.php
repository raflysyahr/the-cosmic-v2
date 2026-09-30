<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('discuss_rooms', function (Blueprint $table) {
            $table->string('context_id', 191)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('discuss_rooms', function (Blueprint $table) {
            $table->ulid('context_id')->nullable()->change();
        });
    }
};
