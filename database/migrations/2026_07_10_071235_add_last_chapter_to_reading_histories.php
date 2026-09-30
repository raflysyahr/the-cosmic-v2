<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reading_histories', function (Blueprint $table) {
            $table->integer('last_chapter_index')->nullable()->after('read_chapters');
            $table->string('last_chapter_url')->nullable()->after('last_chapter_index');
        });

        $entries = DB::table('reading_histories')->whereNotNull('read_chapters')->get();

        foreach ($entries as $entry) {
            $chapters = json_decode($entry->read_chapters, true) ?? [];
            if (empty($chapters)) continue;
            $last = end($chapters);

            DB::table('reading_histories')
                ->where('id', $entry->id)
                ->update(['last_chapter_index' => $last]);
        }
    }

    public function down(): void
    {
        Schema::table('reading_histories', function (Blueprint $table) {
            $table->dropColumn(['last_chapter_index', 'last_chapter_url']);
        });
    }
};
