<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Tanda "Helpful" dan "Best Answer" pada pesan. Helpful boleh banyak
        // penanda per pesan; Best Answer maksimal satu per pertanyaan
        // (dijaga di MarkService, bukan constraint, karena "pertanyaan" =
        // pesan induk dari reply_to_id).
        Schema::create('discuss_message_marks', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('room_id');
            $table->ulid('message_id');
            $table->ulid('marked_by');
            $table->string('kind', 20);
            $table->timestamp('created_at')->nullable();

            $table->unique(['message_id', 'marked_by', 'kind']);
            $table->index(['message_id', 'kind']);
            $table->index(['marked_by', 'kind', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('discuss_message_marks');
    }
};
