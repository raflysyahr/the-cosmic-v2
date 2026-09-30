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
            $table->json('read_chapters')->nullable()->after('cover_image');
        });

        $groups = DB::table('reading_histories')
            ->select('user_id', 'slug')
            ->selectRaw('MIN(id) as keep_id')
            ->groupBy('user_id', 'slug')
            ->get();

        if ($groups->isNotEmpty()) {
            foreach ($groups as $row) {
                $chapters = DB::table('reading_histories')
                    ->where('user_id', $row->user_id)
                    ->where('slug', $row->slug)
                    ->orderBy('chapter_index')
                    ->pluck('chapter_index')
                    ->toJson();

                DB::table('reading_histories')
                    ->where('id', $row->keep_id)
                    ->update(['read_chapters' => $chapters]);
            }

            $keepIds = $groups->pluck('keep_id');
            DB::table('reading_histories')
                ->whereNotIn('id', $keepIds)
                ->delete();
        }

        Schema::table('reading_histories', function (Blueprint $table) {
            $table->dropUnique(['user_id', 'slug', 'chapter_index']);
            $table->dropColumn(['chapter_index', 'chapter_title', 'chapter_url']);
            $table->unique(['user_id', 'slug']);
        });
    }

    public function down(): void
    {
        Schema::table('reading_histories', function (Blueprint $table) {
            $table->integer('chapter_index')->nullable()->after('cover_image');
            $table->string('chapter_title')->nullable()->after('read_chapters');
            $table->string('chapter_url')->nullable()->after('chapter_title');
        });

        $entries = DB::table('reading_histories')
            ->whereNotNull('read_chapters')
            ->get();

        foreach ($entries as $entry) {
            $chapters = json_decode($entry->read_chapters, true) ?? [];
            foreach ($chapters as $index => $chapterIdx) {
                $data = [
                    'id' => $index === 0 ? $entry->id : null,
                    'user_id' => $entry->user_id,
                    'slug' => $entry->slug,
                    'title' => $entry->title,
                    'cover_image' => $entry->cover_image,
                    'chapter_index' => $chapterIdx,
                ];

                if ($index === 0) {
                    DB::table('reading_histories')
                        ->where('id', $entry->id)
                        ->update($data);
                } else {
                    DB::table('reading_histories')->insert($data);
                }
            }
        }

        Schema::table('reading_histories', function (Blueprint $table) {
            $table->dropUnique(['user_id', 'slug']);
            $table->dropColumn('read_chapters');
            $table->unique(['user_id', 'slug', 'chapter_index']);
        });
    }
};
