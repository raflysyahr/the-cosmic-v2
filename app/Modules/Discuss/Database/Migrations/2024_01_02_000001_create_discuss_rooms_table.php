<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('discuss_rooms', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('slug', 100)->unique();
            $table->string('name', 100);
            $table->text('description')->nullable();
            $table->text('cover_url')->nullable();
            $table->string('type', 20)->default('public');
            $table->ulid('owner_user_id');
            $table->string('context_type', 50)->nullable();
            $table->ulid('context_id')->nullable();
            $table->boolean('is_active')->default(true);
            $table->json('settings')->default('{}');
            $table->timestamps();

            $table->index(['context_type', 'context_id']);
            $table->index('owner_user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('discuss_rooms');
    }
};
