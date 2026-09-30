<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('discuss_emotes', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('room_id')->nullable();
            $table->string('code', 50)->unique();
            $table->string('name', 100);
            $table->text('image_url');
            $table->boolean('is_animated')->default(false);
            $table->boolean('is_active')->default(true);
            $table->ulid('uploaded_by_user_id')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index('room_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('discuss_emotes');
    }
};
