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

    // --- Fase 2 -----------------------------------------------------------

    // Helpful: member lain menandai pesan berguna. CP diberikan SEKALI per
    // pesan (tanda pertama); tanda berikutnya hanya menambah hitungan.
    'helpful_points' => 15,
    // Penanda harus sudah jadi member room minimal sekian jam dan punya
    // email terverifikasi; maks sekian tanda Helpful yang boleh diberikan
    // satu orang per hari.
    'helpful_min_member_hours' => 24,
    'helpful_daily_give_limit' => 10,

    // Best Answer: ditandai penulis pesan yang dibalas (penanya) atau
    // moderator/admin room. Maks satu per pertanyaan.
    'best_answer_points' => 25,

    // Bonus harian: sekali per hari (semua room digabung). Memenuhi syarat
    // kalau hari ini sudah punya >= min_replies reply valid ATAU
    // >= min_messages pesan valid. Isi 0 untuk mematikan salah satu jalur;
    // kalau keduanya 0, bonus tidak pernah diberikan.
    'daily_bonus_points' => 10,
    'daily_bonus_min_replies' => 1,
    'daily_bonus_min_messages' => 5,

    // Streak = hari berturut-turut yang mendapat bonus harian. Bonus
    // diberikan sekali per milestone per rangkaian streak. Streak dihitung
    // dari log bonus harian, jadi daily_bonus_points = 0 juga menghentikan
    // streak.
    'streak_3_points' => 10,
    'streak_7_points' => 25,
    'streak_14_points' => 50,
    'streak_30_points' => 100,


    // --- Fase 3 -----------------------------------------------------------

    // Achievement: bonus sekali seumur hidup per user (semua room digabung).
    // Ambang ada di AchievementService::DEFINITIONS; di sini hanya poinnya.
    // Isi 0 untuk menonaktifkan satu achievement.
    'achievement_first_reply_points' => 20,
    'achievement_replies_100_points' => 50,
    'achievement_likes_100_points' => 50,
    'achievement_helpful_10_points' => 100,
    'achievement_best_answer_10_points' => 100,
    'achievement_active_30_points' => 100,

    // Report: pelapor dapat poin HANYA jika moderator menilai laporannya
    // valid. Maks report_daily_limit laporan per orang per hari.
    'report_valid_points' => 5,
    'report_daily_limit' => 5,

    // Penalti yang bisa dijatuhkan moderator saat menutup laporan valid.
    // Saldo tidak turun di bawah 0 dan rank tidak diturunkan.
    'penalty_spam_points' => 10,
    'penalty_manipulation_points' => 50,
];
