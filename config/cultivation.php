<?php

// Konfigurasi Cosmic Cultivation System — nilai XP/resource per sumber.
// Admin bisa ubah lewat .env tanpa perlu edit kode PHP apa pun.
// Tambahkan sumber baru di sini kapan saja (mis. CULTIVATION_XP_LOGIN
// untuk daily login) — CultivationService::addProgress() menerima
// $source & $amount bebas, tidak terikat pada daftar di file ini.

return [
    'xp' => [
        // Dibaca ReadingHistoryService::markRead() — diberikan sekali per
        // chapter unik yang dibaca (dicegah dobel lewat
        // cultivation_progress_logs, bukan lewat pengecekan di sini).
        'chapter_read' => (int) env('CULTIVATION_XP_CHAPTER_READ', 10),

        // Disiapkan untuk modul Comment yang menyusul terpisah — belum
        // ada pemanggil di kode saat ini.
        'comment' => (int) env('CULTIVATION_XP_COMMENT', 5),

        // Disiapkan untuk modul Mission Board yang menyusul terpisah —
        // ini nilai DEFAULT kalau mission tidak menentukan reward sendiri;
        // mission board biasanya override lewat parameter $amount saat
        // memanggil CultivationService::addProgress() langsung.
        'mission_default' => (int) env('CULTIVATION_XP_MISSION_DEFAULT', 50),
    ],
];
