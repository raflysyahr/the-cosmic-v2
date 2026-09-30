<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('discuss_reactions', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('message_id');
            $table->ulid('user_id');
            $table->ulid('emote_id');
            $table->timestamp('created_at')->nullable();

            $table->unique(['message_id', 'user_id', 'emote_id']);
            $table->index('message_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('discuss_reactions');
    }
};
