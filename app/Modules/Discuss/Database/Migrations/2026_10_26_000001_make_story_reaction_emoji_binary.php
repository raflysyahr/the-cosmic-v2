<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Kolom `emoji` tabel reaksi story memakai collation bawaan koneksi (utf8mb4_unicode_ci).
 * Di MySQL/MariaDB collation itu menganggap SEMUA emoji di luar BMP (👍 😂 😮 😢 🔥) sama.
 * Akibatnya `GROUP BY emoji` menggabungkan reaksi-reaksi itu menjadi satu baris, sehingga
 * di UI hanya satu emoji (biasanya 👍) yang muncul dengan jumlah gabungan, dan menekan/
 * membatalkan reaksi terasa "salah". SQLite membandingkan byte per byte, jadi tidak terkena
 * (itulah kenapa test lulus).
 *
 * utf8mb4_bin membandingkan byte per byte. Data yang sudah ada tidak berubah; hanya cara
 * membandingkannya yang dibetulkan, jadi hitungan langsung benar setelah migrasi.
 */
return new class extends Migration
{
    private function isMysqlFamily(): bool
    {
        return in_array(DB::getDriverName(), ['mysql', 'mariadb'], true);
    }

    public function up(): void
    {
        if (! $this->isMysqlFamily()) {
            return;
        }

        $table = DB::getTablePrefix() . 'discuss_announcement_reactions';

        DB::statement("ALTER TABLE `{$table}` MODIFY `emoji` VARCHAR(16) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL");
    }

    public function down(): void
    {
        if (! $this->isMysqlFamily()) {
            return;
        }

        $table = DB::getTablePrefix() . 'discuss_announcement_reactions';

        DB::statement("ALTER TABLE `{$table}` MODIFY `emoji` VARCHAR(16) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL");
    }
};
