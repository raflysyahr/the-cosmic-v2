<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('discuss_messages', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('room_id');
            $table->ulid('user_id');
            $table->ulid('reply_to_id')->nullable();
            $table->string('type', 20)->default('text');
            $table->text('body')->nullable();
            $table->json('attachments')->default('[]');
            $table->boolean('is_edited')->default(false);
            $table->boolean('is_deleted')->default(false);
            $table->ulid('deleted_by_user_id')->nullable();
            $table->json('metadata')->default('{}');
            $table->timestamps();

            $table->index(['room_id', 'created_at']);
            $table->index('user_id');
            $table->index('reply_to_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('discuss_messages');
    }
};
