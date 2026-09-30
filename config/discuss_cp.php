<?php

// Contribution Points (CP) Discuss — nilai DEFAULT. Semua key di sini bisa
// di-override admin lewat API (PUT /api/admin/cp/settings) tanpa edit kode;
// override disimpan di tabel `discuss_cp_settings`, bukan .env, supaya bisa
// diubah saat aplikasi berjalan. Key yang tidak di-override memakai nilai ini.
//
// Semua batas (daily_cap, per-message cap, tier) dihitung dari POIN DASAR,
// sebelum dikali multiplier event. Jadi event "x2" menggandakan payout,
// bukan melonggarkan batas anti-farming.

return [
    'enabled' => true,

    // Pesan biasa (bukan reply ke orang lain): poin kecil + batas harian.
    'message_points' => 1,
    'message_daily_cap' => 20,

    // Reply ke pesan orang lain, per hari: #1..tier1_limit = tier1_points,
    // sampai tier2_limit = tier2_points, setelah itu 0.
    'reply_tier1_limit' => 10,
    'reply_tier1_points' => 4,
    'reply_tier2_limit' => 20,
    'reply_tier2_points' => 2,

    // Penulis pesan dapat poin saat orang lain memberi reaksi. Maks per pesan.
    'reaction_points' => 2,
    'reaction_cap_per_message' => 100,
    // Hanya hitung reaksi dari akun dengan email terverifikasi.
    'require_verified_reactor' => true,

    // Penulis pesan dapat poin saat orang lain me-reply pesannya.
    'reply_received_points' => 1,
    'reply_received_cap_per_message' => 50,

    // Anti-spam. Lebih dari burst_count pesan dalam burst_window_seconds =
    // tidak dapat CP. Isi yang sama persis dalam duplicate_window_hours
    // jam = tidak dapat CP. Teks dengan huruf/angka < min_alnum_chars
    // (mis. hanya emoji) dan tanpa lampiran = tidak dapat CP.
    'burst_count' => 10,
    'burst_window_seconds' => 20,
    'duplicate_window_hours' => 24,
    'min_alnum_chars' => 2,
];
