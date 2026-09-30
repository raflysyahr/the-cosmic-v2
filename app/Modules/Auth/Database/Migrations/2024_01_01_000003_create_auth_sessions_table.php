<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('auth_sessions', function (Blueprint $table) {
            $table->string('id', 100)->primary();
            $table->ulid('user_id')->nullable();
            $table->string('ip_address', 45);
            $table->text('user_agent');
            $table->longText('payload');
            $table->integer('last_activity');

            $table->index('user_id');
            $table->index('last_activity');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('auth_sessions');
    }
};
