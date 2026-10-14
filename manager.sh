#!/data/data/com.termux/files/usr/bin/bash

# ============================================================
# THE COSMIC DEV MANAGER
#
# npm        -> Termux
# reverb     -> Termux
# queue      -> Termux
# mariadb    -> Termux
# wake-lock  -> Termux
# server     -> Ubuntu / proot-distro
# ============================================================

# Folder tempat manager.sh berada
PROJECT_DIR="$(cd "$(dirname "$0")" && pwd)"

PID_DIR="$HOME/.the-cosmic-pids"
LOG_DIR="$HOME/.the-cosmic-logs"

mkdir -p "$PID_DIR" "$LOG_DIR"

# ============================================================
# COLORS
# ============================================================

RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
CYAN='\033[0;36m'
BLUE='\033[0;34m'
NC='\033[0m'

# ============================================================
# PID / LOG
# ============================================================

pid_file() {
    echo "$PID_DIR/$1.pid"
}

log_file() {
    echo "$LOG_DIR/$1.log"
}

# ============================================================
# WAKE LOCK
# ============================================================

start_wake_lock() {

    if ! command -v termux-wake-lock >/dev/null 2>&1; then
        echo -e "${RED}[wake-lock] termux-wake-lock tidak ditemukan.${NC}"
        echo "Pastikan paket termux-tools tersedia."
        return 1
    fi

    echo -e "${CYAN}[wake-lock] mengaktifkan wake lock...${NC}"

    if termux-wake-lock; then
        touch "$PID_DIR/wake-lock.active"
        echo -e "${GREEN}[wake-lock] perintah berhasil dijalankan.${NC}"
    else
        echo -e "${RED}[wake-lock] gagal mengaktifkan wake lock.${NC}"
        return 1
    fi
}

stop_wake_lock() {

    if ! command -v termux-wake-unlock >/dev/null 2>&1; then
        echo -e "${RED}[wake-lock] termux-wake-unlock tidak ditemukan.${NC}"
        return 1
    fi

    echo -e "${CYAN}[wake-lock] menonaktifkan wake lock...${NC}"

    if termux-wake-unlock; then
        rm -f "$PID_DIR/wake-lock.active"
        echo -e "${GREEN}[wake-lock] dinonaktifkan.${NC}"
    else
        echo -e "${RED}[wake-lock] gagal menonaktifkan wake lock.${NC}"
        return 1
    fi
}

status_wake_lock() {

    if [[ -f "$PID_DIR/wake-lock.active" ]]; then
        echo -e "${GREEN}● wake-lock${NC} : ACTIVE (dicatat manager)"
    else
        echo -e "${YELLOW}● wake-lock${NC} : INACTIVE / UNKNOWN"
    fi
}

# ============================================================
# CHECK PROCESS
# ============================================================

is_running() {

    local name="$1"
    local file

    file="$(pid_file "$name")"

    if [[ ! -f "$file" ]]; then
        return 1
    fi

    local pid
    pid="$(cat "$file")"

    if [[ ! "$pid" =~ ^[0-9]+$ ]]; then
        rm -f "$file"
        return 1
    fi

    if kill -0 "$pid" 2>/dev/null; then
        return 0
    fi

    rm -f "$file"

    return 1
}

# ============================================================
# MARIADB - TERMUX
# ============================================================

start_mariadb() {

    if ! command -v mariadbd >/dev/null 2>&1; then
        echo -e "${RED}[mariadb] mariadbd tidak ditemukan.${NC}"
        echo "Install MariaDB terlebih dahulu."
        return 1
    fi

    # Cegah menjalankan instance kedua.
    if pgrep -x mariadbd >/dev/null 2>&1; then
        echo -e "${YELLOW}[mariadb] instance MariaDB sudah berjalan.${NC}"
        return 0
    fi

    echo -e "${CYAN}[mariadb] starting...${NC}"

    # Jalankan MariaDB di background.
    mariadbd >> "$(log_file mariadb)" 2>&1 &

    local pid=$!

    echo "$pid" > "$(pid_file mariadb)"

    # Tunggu proses memulai server.
    local i=0

    while (( i < 10 )); do

        if ! kill -0 "$pid" 2>/dev/null; then
            break
        fi

        if mariadb-admin ping --silent >/dev/null 2>&1; then
            echo -e "${GREEN}[mariadb] RUNNING (PID $pid)${NC}"
            return 0
        fi

        sleep 1
        ((i++))

    done

    # Periksa kembali apakah server sudah menerima koneksi.
    if kill -0 "$pid" 2>/dev/null &&
       mariadb-admin ping --silent >/dev/null 2>&1; then

        echo -e "${GREEN}[mariadb] RUNNING (PID $pid)${NC}"
        return 0

    fi

    echo -e "${RED}[mariadb] belum siap atau gagal dijalankan.${NC}"
    echo
    echo -e "${YELLOW}===== MARIADB LOG =====${NC}"

    tail -30 "$(log_file mariadb)" 2>/dev/null

    # Jangan menghapus PID jika proses masih hidup.
    # Pengguna perlu memeriksa log dan konfigurasi terlebih dahulu.
    if ! kill -0 "$pid" 2>/dev/null; then
        rm -f "$(pid_file mariadb)"
    fi

    return 1
}

stop_mariadb() {

    if ! pgrep -x mariadbd >/dev/null 2>&1; then

        rm -f "$(pid_file mariadb)"

        echo -e "${YELLOW}[mariadb] tidak berjalan.${NC}"

        return 0
    fi

    echo -e "${CYAN}[mariadb] stopping...${NC}"

    # Prioritaskan shutdown yang bersih melalui mariadb-admin.
    if command -v mariadb-admin >/dev/null 2>&1; then

        if mariadb-admin shutdown >/dev/null 2>&1; then

            local i=0

            while (( i < 15 )); do

                if ! pgrep -x mariadbd >/dev/null 2>&1; then
                    break
                fi

                sleep 1
                ((i++))

            done

        fi

    fi

    # Jika masih berjalan, kirim SIGTERM hanya kepada PID
    # yang disimpan manager dan masih valid.
    if pgrep -x mariadbd >/dev/null 2>&1; then

        if [[ -f "$(pid_file mariadb)" ]]; then

            local pid
            pid="$(cat "$(pid_file mariadb)")"

            if [[ "$pid" =~ ^[0-9]+$ ]] &&
               kill -0 "$pid" 2>/dev/null; then

                echo -e "${YELLOW}[mariadb] mengirim SIGTERM ke PID $pid...${NC}"

                kill -TERM "$pid" 2>/dev/null

            fi

        else

            echo -e "${RED}[mariadb] PID manager tidak tersedia.${NC}"
            echo "Tidak menghentikan proses yang tidak dikelola manager."
            return 1

        fi

        local i=0

        while (( i < 10 )); do

            if ! pgrep -x mariadbd >/dev/null 2>&1; then
                break
            fi

            sleep 1
            ((i++))

        done

    fi

    if pgrep -x mariadbd >/dev/null 2>&1; then

        echo -e "${RED}[mariadb] masih berjalan.${NC}"
        echo "Periksa konfigurasi dan log MariaDB."
        echo "Tidak melakukan kill -9 secara otomatis."

        return 1

    fi

    rm -f "$(pid_file mariadb)"

    echo -e "${GREEN}[mariadb] STOPPED${NC}"
}

# ============================================================
# START PROCESS
# ============================================================

start_process() {

    local name="$1"

    case "$name" in
        wake-lock)
            start_wake_lock
            return $?
            ;;
        mariadb)
            start_mariadb
            return $?
            ;;
    esac

    if is_running "$name"; then
        echo -e "${YELLOW}[$name] sudah berjalan.${NC}"
        return 0
    fi

    echo -e "${CYAN}[$name] starting...${NC}"

    case "$name" in

        # ----------------------------------------------------
        # NPM - TERMUX
        # ----------------------------------------------------

        npm)

            cd "$PROJECT_DIR" || return 1

            setsid npm run dev \
                >> "$(log_file npm)" 2>&1 &

            ;;

        # ----------------------------------------------------
        # REVERB - TERMUX
        # ----------------------------------------------------

        reverb)

            cd "$PROJECT_DIR" || return 1

            setsid php artisan reverb:start \
                >> "$(log_file reverb)" 2>&1 &

            ;;

        # ----------------------------------------------------
        # QUEUE - TERMUX
        # ----------------------------------------------------

        queue)

            cd "$PROJECT_DIR" || return 1

            setsid php artisan queue:listen \
                >> "$(log_file queue)" 2>&1 &

            ;;

        # ----------------------------------------------------
        # LARAVEL SERVER - UBUNTU
        # ----------------------------------------------------

        server)

            setsid proot-distro login ubuntu -- zsh -c \
                'cd ~/auto/the-cosmic && php artisan serve --host=0.0.0.0 --port=8000' \
                >> "$(log_file server)" 2>&1 &

            ;;

        *)

            echo -e "${RED}Process tidak dikenal: $name${NC}"
            return 1

            ;;

    esac

    local pid=$!

    echo "$pid" > "$(pid_file "$name")"

    sleep 2

    if is_running "$name"; then

        echo -e "${GREEN}[$name] RUNNING (PID $pid)${NC}"

    else

        echo -e "${RED}[$name] GAGAL DIJALANKAN${NC}"

        echo
        echo -e "${YELLOW}===== $name LOG =====${NC}"

        tail -30 "$(log_file "$name")" 2>/dev/null

        echo

        rm -f "$(pid_file "$name")"

        return 1

    fi
}

# ============================================================
# STOP PROCESS
# ============================================================

stop_process() {

    local name="$1"

    case "$name" in
        wake-lock)
            stop_wake_lock
            return $?
            ;;
        mariadb)
            stop_mariadb
            return $?
            ;;
    esac

    local file
    file="$(pid_file "$name")"

    if [[ ! -f "$file" ]]; then

        echo -e "${YELLOW}[$name] tidak berjalan.${NC}"

        return 0

    fi

    local pid
    pid="$(cat "$file")"

    if [[ ! "$pid" =~ ^[0-9]+$ ]]; then

        rm -f "$file"

        echo -e "${RED}[$name] PID tidak valid.${NC}"

        return 1

    fi

    if ! kill -0 "$pid" 2>/dev/null; then

        rm -f "$file"

        echo -e "${YELLOW}[$name] sudah berhenti.${NC}"

        return 0

    fi

    echo -e "${CYAN}[$name] stopping PID $pid...${NC}"

    # Matikan process group untuk layanan yang dijalankan
    # menggunakan setsid.
    kill -- "-$pid" 2>/dev/null

    sleep 2

    if kill -0 "$pid" 2>/dev/null; then

        echo -e "${YELLOW}[$name] forcing stop...${NC}"

        kill -TERM -- "-$pid" 2>/dev/null

        sleep 2

    fi

    if kill -0 "$pid" 2>/dev/null; then

        echo -e "${RED}[$name] gagal dihentikan.${NC}"

        return 1

    fi

    rm -f "$file"

    echo -e "${GREEN}[$name] STOPPED${NC}"
}

# ============================================================
# RESTART
# ============================================================

restart_process() {

    local name="$1"

    stop_process "$name"

    local result=$?

    if [[ $result -ne 0 ]]; then
        echo -e "${RED}[$name] restart dibatalkan karena gagal berhenti.${NC}"
        return 1
    fi

    sleep 1

    start_process "$name"
}

# ============================================================
# STATUS
# ============================================================

status_process() {

    local name="$1"

    if is_running "$name"; then

        local pid
        pid="$(cat "$(pid_file "$name")")"

        echo -e "${GREEN}● $name${NC} : RUNNING (PID $pid)"

    else

        echo -e "${RED}● $name${NC} : STOPPED"

    fi
}

status_mariadb() {

    if pgrep -x mariadbd >/dev/null 2>&1; then

        if command -v mariadb-admin >/dev/null 2>&1 &&
           mariadb-admin ping --silent >/dev/null 2>&1; then

            echo -e "${GREEN}● mariadb${NC} : RUNNING (database menerima koneksi)"

        else

            echo -e "${YELLOW}● mariadb${NC} : PROCESS RUNNING, belum terverifikasi menerima koneksi"

        fi

    else

        echo -e "${RED}● mariadb${NC} : STOPPED"

    fi
}

status_all() {

    echo
    echo -e "${BLUE}========================================${NC}"
    echo -e "${BLUE}       THE COSMIC PROCESS STATUS       ${NC}"
    echo -e "${BLUE}========================================${NC}"

    status_wake_lock
    status_mariadb
    status_process npm
    status_process reverb
    status_process queue
    status_process server

    echo
}

# ============================================================
# START ALL
# ============================================================

start_all() {

    echo
    echo -e "${CYAN}Starting The Cosmic...${NC}"
    echo

    start_wake_lock
    start_process mariadb
    start_process npm
    start_process reverb
    start_process queue
    start_process server

    echo

    status_all
}

# ============================================================
# STOP ALL
# ============================================================

stop_all() {

    echo
    echo -e "${CYAN}Stopping The Cosmic...${NC}"
    echo

    stop_process server
    stop_process queue
    stop_process reverb
    stop_process npm
    stop_process mariadb
    stop_wake_lock

    echo

    status_all
}

# ============================================================
# RESTART ALL
# ============================================================

restart_all() {

    stop_all

    local result=$?

    if [[ $result -ne 0 ]]; then
        echo -e "${RED}Ada layanan yang gagal dihentikan.${NC}"
        echo "Periksa status sebelum memulai ulang."
        return 1
    fi

    sleep 2

    start_all
}

# ============================================================
# SHOW LOG
# ============================================================

show_log() {

    local name="$1"

    local file
    file="$(log_file "$name")"

    if [[ ! -f "$file" ]]; then

        echo -e "${YELLOW}Log belum tersedia.${NC}"

        return

    fi

    echo -e "${CYAN}===== $name LOG =====${NC}"
    echo -e "${YELLOW}CTRL+C untuk keluar${NC}"
    echo

    tail -f "$file"
}

# ============================================================
# MENU
# ============================================================

menu() {

    while true; do

        clear

        echo
        echo -e "${BLUE}========================================${NC}"
        echo -e "${BLUE}        THE COSMIC DEV MANAGER         ${NC}"
        echo -e "${BLUE}========================================${NC}"

        echo
        echo "Project:"
        echo "$PROJECT_DIR"

        status_all

        echo "1)  Start ALL"
        echo "2)  Stop ALL"
        echo "3)  Restart ALL"
        echo
        echo "4)  Start npm"
        echo "5)  Stop npm"
        echo "6)  Restart npm"
        echo
        echo "7)  Start Reverb"
        echo "8)  Stop Reverb"
        echo "9)  Restart Reverb"
        echo
        echo "10) Start Queue"
        echo "11) Stop Queue"
        echo "12) Restart Queue"
        echo
        echo "13) Start Laravel Server"
        echo "14) Stop Laravel Server"
        echo "15) Restart Laravel Server"
        echo
        echo "16) Lihat Log"
        echo "17) Status"
        echo
        echo "18) Start MariaDB"
        echo "19) Stop MariaDB"
        echo "20) Restart MariaDB"
        echo
        echo "21) Aktifkan Wake Lock"
        echo "22) Nonaktifkan Wake Lock"
        echo
        echo "0)  Keluar"
        echo

        read -rp "Pilih: " choice

        case "$choice" in

            1)
                start_all
                read -rp "Enter..."
                ;;

            2)
                stop_all
                read -rp "Enter..."
                ;;

            3)
                restart_all
                read -rp "Enter..."
                ;;

            4)
                start_process npm
                read -rp "Enter..."
                ;;

            5)
                stop_process npm
                read -rp "Enter..."
                ;;

            6)
                restart_process npm
                read -rp "Enter..."
                ;;

            7)
                start_process reverb
                read -rp "Enter..."
                ;;

            8)
                stop_process reverb
                read -rp "Enter..."
                ;;

            9)
                restart_process reverb
                read -rp "Enter..."
                ;;

            10)
                start_process queue
                read -rp "Enter..."
                ;;

            11)
                stop_process queue
                read -rp "Enter..."
                ;;

            12)
                restart_process queue
                read -rp "Enter..."
                ;;

            13)
                start_process server
                read -rp "Enter..."
                ;;

            14)
                stop_process server
                read -rp "Enter..."
                ;;

            15)
                restart_process server
                read -rp "Enter..."
                ;;

            16)

                echo
                echo "1) npm"
                echo "2) reverb"
                echo "3) queue"
                echo "4) server"
                echo "5) mariadb"
                echo

                read -rp "Pilih log: " logchoice

                case "$logchoice" in
                    1) show_log npm ;;
                    2) show_log reverb ;;
                    3) show_log queue ;;
                    4) show_log server ;;
                    5) show_log mariadb ;;
                    *) echo "Tidak valid." ;;
                esac

                read -rp "Enter..."

                ;;

            17)

                status_all

                read -rp "Enter..."

                ;;

            18)
                start_process mariadb
                read -rp "Enter..."
                ;;

            19)
                stop_process mariadb
                read -rp "Enter..."
                ;;

            20)
                restart_process mariadb
                read -rp "Enter..."
                ;;

            21)
                start_wake_lock
                read -rp "Enter..."
                ;;

            22)
                stop_wake_lock
                read -rp "Enter..."
                ;;

            0)

                exit 0

                ;;

            *)

                echo -e "${RED}Pilihan tidak valid.${NC}"

                sleep 1

                ;;

        esac

    done
}

# ============================================================
# COMMAND LINE
# ============================================================

case "$1" in

    start)

        if [[ -n "$2" ]]; then
            start_process "$2"
        else
            start_all
        fi

        ;;

    stop)

        if [[ -n "$2" ]]; then
            stop_process "$2"
        else
            stop_all
        fi

        ;;

    restart)

        if [[ -n "$2" ]]; then
            restart_process "$2"
        else
            restart_all
        fi

        ;;

    status)

        status_all

        ;;

    logs)

        if [[ -n "$2" ]]; then
            show_log "$2"
        else
            echo "Penggunaan: $0 logs <npm|reverb|queue|server|mariadb>"
            exit 1
        fi

        ;;

    *)

        menu

        ;;

esac
