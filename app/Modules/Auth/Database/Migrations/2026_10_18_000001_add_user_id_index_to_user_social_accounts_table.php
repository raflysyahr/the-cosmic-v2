<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Index untuk mencari akun social milik user (mis. daftar "akun tertaut" di profil).
    // Foreign key sengaja tidak ditambahkan di sini: menambahkannya lewat ALTER TABLE tidak
    // portabel (SQLite) dan akan gagal bila sudah ada baris yatim.
    public function up(): void
    {
        Schema::table('user_social_accounts', function (Blueprint $table) {
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::table('user_social_accounts', function (Blueprint $table) {
            $table->dropIndex(['user_id']);
        });
    }
};
