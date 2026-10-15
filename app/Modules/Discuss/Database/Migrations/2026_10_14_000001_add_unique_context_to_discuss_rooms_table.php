<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Satu konteks (mis. DM dua user, atau satu komik) hanya boleh punya satu room.
 *
 * Tanpa unique index, dua request bersamaan bisa membuat DM ganda, dan room yang
 * "mengaku" sebagai DM orang lain tidak tertahan di level database. Baris dengan
 * context NULL (room biasa) tidak terpengaruh: NULL tidak dianggap duplikat.
 */
return new class extends Migration
{
    public function up(): void
    {
        $duplicates = DB::table('discuss_rooms')
            ->select('context_type', 'context_id', DB::raw('COUNT(*) as total'))
            ->whereNotNull('context_type')
            ->whereNotNull('context_id')
            ->groupBy('context_type', 'context_id')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        if ($duplicates->isNotEmpty()) {
            $list = $duplicates
                ->map(fn ($row) => "{$row->context_type}:{$row->context_id} (x{$row->total})")
                ->implode(', ');

            throw new RuntimeException(
                'Tidak bisa menambah unique index (context_type, context_id) karena sudah ada duplikat: '
                . $list . '. Periksa room-room tersebut, kosongkan context room yang menyamar atau '
                . 'duplikat (context_type/context_id = NULL), lalu jalankan ulang migration.'
            );
        }

        // Unique dulu, baru index biasa dibuang: kalau langkah pertama gagal, query lookup tetap berindeks.
        Schema::table('discuss_rooms', function (Blueprint $table) {
            $table->unique(['context_type', 'context_id']);
        });

        Schema::table('discuss_rooms', function (Blueprint $table) {
            $table->dropIndex(['context_type', 'context_id']);
        });
    }

    public function down(): void
    {
        Schema::table('discuss_rooms', function (Blueprint $table) {
            $table->index(['context_type', 'context_id']);
        });

        Schema::table('discuss_rooms', function (Blueprint $table) {
            $table->dropUnique(['context_type', 'context_id']);
        });
    }
};
