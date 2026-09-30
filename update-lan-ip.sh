#!/bin/bash
# Deteksi IP LAN aktif (Termux/Android) dan update baris terkait di .env
# secara otomatis, supaya tidak perlu edit manual tiap kali IP berubah
# (misal ganti WiFi/hotspot).
#
# Jalankan dari root project Laravel, SEBELUM start composer dev:
#   bash update-lan-ip.sh
#
# Setelah ini selesai, restart composer dev supaya Vite baca .env baru.

set -e

# Ambil IP LAN aktif — coba beberapa cara karena `ip`/`ifconfig` kadang
# tidak lengkap di Termux, dan urutan interface bisa beda tiap device.
IP=$(ip route get 1.1.1.1 2>/dev/null | grep -oP 'src \K\S+' || true)

if [ -z "$IP" ]; then
    IP=$(ifconfig 2>/dev/null | grep -A1 'wlan0\|rmnet' | grep 'inet ' | awk '{print $2}' | head -1)
fi

if [ -z "$IP" ]; then
    echo "Gagal deteksi IP otomatis. Cek manual dengan: ip addr show"
    exit 1
fi

echo "IP terdeteksi: $IP"

ENV_FILE=".env"
if [ ! -f "$ENV_FILE" ]; then
    echo "File .env tidak ditemukan di direktori ini."
    exit 1
fi

update_env_line() {
    local key="$1"
    local value="$2"
    if grep -q "^${key}=" "$ENV_FILE"; then
        sed -i "s|^${key}=.*|${key}=${value}|" "$ENV_FILE"
    else
        echo "${key}=${value}" >> "$ENV_FILE"
    fi
    echo "  ${key}=${value}"
}

echo "Update .env:"
update_env_line "APP_URL" "http://${IP}:8000"
update_env_line "REVERB_HOST" "${IP}"
update_env_line "VITE_REVERB_HOST" "${IP}"
update_env_line "VITE_DEV_SERVER_ORIGIN" "http://${IP}:5173"
update_env_line "VITE_DEV_SERVER_HOST" "${IP}"

# SANCTUM_STATEFUL_DOMAINS perlu IP baru ditambahkan (bukan diganti total,
# supaya localhost tetap ada untuk akses dari device server itu sendiri).
CURRENT_DOMAINS=$(grep "^SANCTUM_STATEFUL_DOMAINS=" "$ENV_FILE" | cut -d= -f2)
if [[ "$CURRENT_DOMAINS" != *"$IP"* ]]; then
    NEW_DOMAINS="${IP}:5173,${IP}:8000,localhost:5173,localhost:8000"
    update_env_line "SANCTUM_STATEFUL_DOMAINS" "$NEW_DOMAINS"
fi

echo ""
echo "Selesai. Restart composer dev sekarang supaya perubahan terbaca:"
echo "  (Ctrl+C proses dev yang lama, lalu jalankan lagi) composer dev"
echo ""
echo "Akses dari device lain lewat: http://${IP}:8000"
