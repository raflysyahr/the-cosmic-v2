#!/data/data/com.termux/files/usr/bin/bash

# ============================================================
# THE COSMIC DEV MANAGER
#
# npm       -> Termux
# reverb    -> Termux
# queue     -> Termux
# server    -> Ubuntu / proot-distro
# ============================================================

# Folder tempat manager.sh berada
PROJECT_DIR="$(cd "$(dirname "$0")" && pwd)"

PID_DIR="$HOME/.the-cosmic-pids"
LOG_DIR="$HOME/.the-cosmic-logs"

mkdir -p "$PID_DIR"
mkdir -p "$LOG_DIR"

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

    if kill -0 "$pid" 2>/dev/null; then
        return 0
    fi

    rm -f "$file"

    return 1
}

# ============================================================
# START PROCESS
# ============================================================

start_process() {

    local name="$1"

    if is_running "$name"; then
        echo -e "${YELLOW}[$name] sudah berjalan.${NC}"
        return
    fi

    echo -e "${CYAN}[$name] starting...${NC}"

    case "$name" in

        # ----------------------------------------------------
        # NPM - TERMUX
        # ----------------------------------------------------

        npm)

            cd "$PROJECT_DIR" || exit 1

            setsid npm run dev \
                >> "$(log_file npm)" 2>&1 &

            ;;

        # ----------------------------------------------------
        # REVERB - TERMUX
        # ----------------------------------------------------

        reverb)

            cd "$PROJECT_DIR" || exit 1

            setsid php artisan reverb:start \
                >> "$(log_file reverb)" 2>&1 &

            ;;

        # ----------------------------------------------------
        # QUEUE - TERMUX
        # ----------------------------------------------------

        queue)

            cd "$PROJECT_DIR" || exit 1

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

    fi
}

# ============================================================
# STOP PROCESS
# ============================================================

stop_process() {

    local name="$1"
    local file

    file="$(pid_file "$name")"

    if [[ ! -f "$file" ]]; then

        echo -e "${YELLOW}[$name] tidak berjalan.${NC}"

        return

    fi

    local pid
    pid="$(cat "$file")"

    if ! kill -0 "$pid" 2>/dev/null; then

        rm -f "$file"

        echo -e "${YELLOW}[$name] sudah berhenti.${NC}"

        return

    fi

    echo -e "${CYAN}[$name] stopping PID $pid...${NC}"

    # Matikan process group
    kill -- "-$pid" 2>/dev/null

    sleep 2

    # Force kill jika masih hidup
    if kill -0 "$pid" 2>/dev/null; then

        echo -e "${YELLOW}[$name] forcing stop...${NC}"

        kill -9 -- "-$pid" 2>/dev/null

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

status_all() {

    echo
    echo -e "${BLUE}========================================${NC}"
    echo -e "${BLUE}       THE COSMIC PROCESS STATUS       ${NC}"
    echo -e "${BLUE}========================================${NC}"

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

    stop_process npm
    stop_process reverb
    stop_process queue
    stop_process server

    echo

    status_all
}

# ============================================================
# RESTART ALL
# ============================================================

restart_all() {

    stop_all

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
                echo

                read -rp "Pilih log: " logchoice

                case "$logchoice" in
                    1) show_log npm ;;
                    2) show_log reverb ;;
                    3) show_log queue ;;
                    4) show_log server ;;
                    *) echo "Tidak valid." ;;
                esac

                ;;

            17)

                status_all

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

        show_log "$2"

        ;;

    *)

        menu

        ;;

esac
