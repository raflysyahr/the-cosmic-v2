<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Laporan pesan oleh member; ditinjau moderator/admin room. Laporan
        // yang dinilai valid memberi CP ke pelapor dan (opsional) penalti
        // ke penulis pesan.
        Schema::create('discuss_reports', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('room_id');
            $table->ulid('message_id');
            // Snapshot penulis pesan saat dilaporkan (pesan bisa dihapus/diedit).
            $table->ulid('message_author_id');
            $table->ulid('reporter_id');
            $table->string('reason', 30);
            $table->string('note', 500)->nullable();
            $table->string('status', 20)->default('pending');
            $table->string('penalty', 20)->nullable();
            $table->ulid('resolved_by')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamp('created_at')->nullable();

            // Satu orang hanya bisa melaporkan satu pesan sekali.
            $table->unique(['message_id', 'reporter_id']);
            $table->index(['room_id', 'status', 'created_at']);
            $table->index(['reporter_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('discuss_reports');
    }
};
