<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('discuss_ranks', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('room_id')->nullable();
            $table->string('name', 50);
            $table->string('label_color', 7);
            $table->text('icon_url')->nullable();
            $table->integer('min_xp')->default(0);
            $table->integer('order')->default(1);
            $table->json('perks')->default('{}');
            $table->timestamp('created_at')->nullable();

            $table->index(['room_id', 'order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('discuss_ranks');
    }
};
