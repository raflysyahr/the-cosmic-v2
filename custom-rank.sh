#!/bin/bash
# Skrip interaktif untuk custom data Cultivation System — tinggal jalankan
# dan isi lewat prompt, tidak perlu edit file manual.
#
# Cara pakai:
#   bash cultivation-db-tool.sh
#
# Password MariaDB akan diminta terpisah tiap query (standar mariadb -p),
# supaya password TIDAK pernah tersimpan di riwayat command / file ini.

set -e

DB_USER="u0_a428"
read -rp "Nama database [thecosmic]: " DB_NAME
DB_NAME="${DB_NAME:-thecosmic}"

run_query() {
    # $1 = deskripsi singkat, $2 = query SQL
    echo ""
    echo "── $1 ──"
    mariadb -u "$DB_USER" -p "$DB_NAME" -e "$2"
}

echo ""
echo "=== Cultivation DB Tool ==="
echo "1) Lihat semua realm (id, nama, urutan)"
echo "2) Cari user_id dari username"
echo "3) Lihat progress cultivation user tertentu"
echo "4) Set realm + stage + progress user tertentu"
echo "5) Tambah progress user tertentu (tanpa breakthrough otomatis)"
echo "6) Reset user ke realm 1 stage 1"
echo "7) Lihat histori XP (progress log) user tertentu"
echo "8) Hapus 1 histori XP tertentu (biar bisa dapat XP itu lagi)"
echo "9) Set SEMUA user ke realm tertentu sekaligus (event/promo)"
read -rp "Pilih menu [1-9]: " MENU

case "$MENU" in
  1)
    run_query "Daftar realm" "
      SELECT id, name, full_name, sort_order, stage_required
      FROM cultivation_realms
      ORDER BY sort_order;
    "
    ;;

  2)
    read -rp "Username yang dicari: " USERNAME
    run_query "Cari user" "
      SELECT id, username, email, display_name
      FROM users
      WHERE username = '${USERNAME}';
    "
    ;;

  3)
    read -rp "Username: " USERNAME
    run_query "Progress cultivation ${USERNAME}" "
      SELECT uc.id, uc.user_id, u.username, uc.realm_id, r.name AS realm_name,
             uc.stage, uc.progress, r.stage_required
      FROM user_cultivation uc
      JOIN users u ON u.id = uc.user_id
      JOIN cultivation_realms r ON r.id = uc.realm_id
      WHERE u.username = '${USERNAME}';
    "
    ;;

  4)
    echo "(Jalankan menu 2 dulu kalau belum tahu user_id, menu 1 kalau belum tahu realm_id)"
    read -rp "user_id: " USER_ID
    read -rp "realm_id: " REALM_ID
    read -rp "stage (1-10): " STAGE
    read -rp "progress (angka, boleh 0): " PROGRESS
    run_query "Set cultivation user ${USER_ID}" "
      UPDATE user_cultivation
      SET realm_id = '${REALM_ID}', stage = ${STAGE}, progress = ${PROGRESS}, updated_at = NOW()
      WHERE user_id = '${USER_ID}';
    "
    echo "Kalau tidak ada baris ter-update (user belum punya row cultivation), pakai INSERT manual:"
    echo "  INSERT INTO user_cultivation (id, user_id, realm_id, stage, progress, created_at, updated_at)"
    echo "  VALUES (REPLACE(UUID(),'-',''), '${USER_ID}', '${REALM_ID}', ${STAGE}, ${PROGRESS}, NOW(), NOW());"
    ;;

  5)
    read -rp "user_id: " USER_ID
    read -rp "Tambah progress sebanyak: " AMOUNT
    run_query "Tambah progress user ${USER_ID}" "
      UPDATE user_cultivation
      SET progress = progress + ${AMOUNT}, updated_at = NOW()
      WHERE user_id = '${USER_ID}';
    "
    echo "Catatan: ini TIDAK otomatis breakthrough stage/realm walau progress"
    echo "melebihi stage_required — breakthrough itu logic aplikasi (PHP), bukan"
    echo "trigger database. Kalau progress sudah lewat, pakai menu 4 buat set manual."
    ;;

  6)
    read -rp "user_id: " USER_ID
    run_query "Reset user ${USER_ID} ke realm 1 stage 1" "
      UPDATE user_cultivation uc
      JOIN cultivation_realms r ON r.sort_order = 1
      SET uc.realm_id = r.id, uc.stage = 1, uc.progress = 0, uc.updated_at = NOW()
      WHERE uc.user_id = '${USER_ID}';
    "
    ;;

  7)
    read -rp "user_id: " USER_ID
    run_query "Histori XP user ${USER_ID}" "
      SELECT id, source, reference, amount, created_at
      FROM cultivation_progress_logs
      WHERE user_id = '${USER_ID}'
      ORDER BY created_at DESC
      LIMIT 20;
    "
    ;;

  8)
    read -rp "user_id: " USER_ID
    read -rp "source (mis. chapter_read): " SOURCE
    read -rp "reference (mis. nama-slug-series:5): " REFERENCE
    run_query "Hapus histori XP user ${USER_ID}" "
      DELETE FROM cultivation_progress_logs
      WHERE user_id = '${USER_ID}' AND source = '${SOURCE}' AND reference = '${REFERENCE}';
    "
    ;;

  9)
    echo "⚠️  Ini akan mengubah SEMUA user sekaligus, tidak bisa di-undo."
    read -rp "Ketik 'YAKIN' untuk lanjut: " CONFIRM
    if [ "$CONFIRM" != "YAKIN" ]; then
      echo "Dibatalkan."
      exit 0
    fi
    read -rp "sort_order realm tujuan (1-20): " SORT_ORDER
    run_query "Set semua user ke realm sort_order=${SORT_ORDER}" "
      UPDATE user_cultivation uc
      JOIN cultivation_realms r ON r.sort_order = ${SORT_ORDER}
      SET uc.realm_id = r.id, uc.stage = 1, uc.progress = 0, uc.updated_at = NOW();
    "
    ;;

  *)
    echo "Pilihan tidak dikenal."
    exit 1
    ;;
esac

echo ""
echo "Selesai."

