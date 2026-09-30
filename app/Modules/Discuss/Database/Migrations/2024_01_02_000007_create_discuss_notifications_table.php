<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('discuss_notifications', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('user_id');
            $table->string('type', 50);
            $table->ulid('actor_user_id')->nullable();
            $table->json('payload')->default('{}');
            $table->boolean('is_read')->default(false);
            $table->timestamp('created_at')->nullable();

            $table->index(['user_id', 'is_read']);
            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('discuss_notifications');
    }
};
