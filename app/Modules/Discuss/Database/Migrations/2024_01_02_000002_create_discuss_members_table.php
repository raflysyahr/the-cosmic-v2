<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('discuss_members', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('room_id');
            $table->ulid('user_id');
            $table->string('role', 20)->default('member');
            $table->ulid('rank_id')->nullable();
            $table->integer('xp_points')->default(0);
            $table->timestamp('muted_until')->nullable();
            $table->boolean('is_banned')->default(false);
            $table->timestamp('last_read_at')->nullable();
            $table->timestamp('joined_at')->nullable();

            $table->unique(['room_id', 'user_id']);
            $table->index('user_id');
            $table->index('rank_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('discuss_members');
    }
};
