<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cultivation_eras', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('name', 100);
            $table->string('resource_name', 100);
            $table->string('resource_slug', 100);
            $table->unsignedInteger('sort_order');
            $table->timestamps();

            $table->unique('sort_order');
            $table->unique('resource_slug');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cultivation_eras');
    }
};
