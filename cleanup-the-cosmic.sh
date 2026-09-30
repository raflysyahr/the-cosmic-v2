#!/usr/bin/env bash
# ============================================================
# The Cosmic — Dead Code & Unused File Cleanup
# Jalankan dari root project Laravel (folder yang ada artisan-nya).
#
# Yang dilakukan skrip ini:
#   1. Hapus 4 file debug/scratch script yang nyasar di root project
#      (bukan bagian dari aplikasi, tidak dipanggil dari mana pun).
#   2. Hapus 4 file frontend yang sudah tidak diimport/dirender oleh
#      file manapun (dead files) — sudah dicek silang terhadap semua
#      import, Inertia::render(), dan routes.
#   3. Bersihkan baris console.log() debug leftover di Room.tsx yang
#      tidak dihapus filenya (filenya masih dipakai), hanya baris
#      debug-nya.
#
# Aman dijalankan sekali jalan — cukup review daftar di bawah dulu.
# ============================================================
set -e

if [ ! -f "artisan" ]; then
  echo "Jalankan skrip ini dari root project Laravel (folder yang ada file 'artisan')." >&2
  exit 1
fi

echo "== 1. Menghapus debug/scratch script di root =="
FILES_ROOT=(
  "test_bookmark.php"
  "test_rh_cleanup.php"
  "test_rh.php"
  "test_cleanup.php"
)
for f in "${FILES_ROOT[@]}"; do
  if [ -f "$f" ]; then
    rm -v "$f"
  else
    echo "  (lewati, tidak ditemukan) $f"
  fi
done

echo ""
echo "== 2. Menghapus file frontend yang tidak terpakai =="
FILES_UNUSED=(
  "resources/js/Pages/Discuss/Backup.tsx"      # halaman lama, tidak pernah di-Inertia::render()
  "resources/js/utils/imageProxy.ts"           # proxyImg() tidak diimport di mana pun
  "resources/js/api/fakeClient.ts"             # sisa eksperimen fake API, series.ts/genres.ts sudah balik ke client.ts asli
  "resources/js/Components/cultivation/Crest.tsx"  # komponen tidak diimport di mana pun
)
for f in "${FILES_UNUSED[@]}"; do
  if [ -f "$f" ]; then
    rm -v "$f"
  else
    echo "  (lewati, tidak ditemukan) $f"
  fi
done

echo ""
echo "== 3. Membersihkan console.log() debug di Room.tsx =="
ROOM_FILE="resources/js/Pages/Discuss/Room.tsx"
if [ -f "$ROOM_FILE" ]; then
  # Hapus baris yang HANYA berisi pemanggilan console.log(...) debug leftover.
  # Pola dicocokkan persis dengan baris yang ditemukan saat audit, supaya
  # tidak menyentuh baris lain yang kebetulan mengandung "console.log".
  sed -i \
    -e '/^\s*console\.log("ch",channel)\s*$/d' \
    -e '/^\s*console\.log("Listen message sent active")\s*$/d' \
    -e '/^\s*console\.log("react",e\.emote_id,e\.action,e\.message_id);\s*$/d' \
    -e '/^\s*console\.log("all message before",messages)\s*$/d' \
    -e '/^\s*console\.log("added > 1",reactions,idx)\s*$/d' \
    -e '/^\s*console\.log("added",reactions,e\.emote_id)\s*$/d' \
    -e '/^\s*console\.log("reactions",{ \.\.\.m,reactions })\s*$/d' \
    -e '/^\s*console\.log("all chat after",messages)\s*$/d' \
    "$ROOM_FILE"
  echo "  Selesai membersihkan $ROOM_FILE"
else
  echo "  (lewati, tidak ditemukan) $ROOM_FILE"
fi

echo ""
echo "== Selesai. =="
echo "Catatan: script dev tooling (manager.sh, custom-rank.sh, update-lan-ip.sh,"
echo "worker-proxy.js) dan FAKE_API_README.md SENGAJA TIDAK dihapus — itu tooling"
echo "operasional yang kamu jalankan manual, bukan dead code. Review manual dulu"
echo "kalau memang mau dibuang."
