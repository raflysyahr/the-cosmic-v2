#!/bin/bash

# ============================================================
# DATABASE MANAGER
# ============================================================

read -rp "Database: " DB

if [ -z "$DB" ]; then
    echo "Database tidak boleh kosong."
    exit 1
fi

echo

read -rp "Search: " SEARCH

if [ -z "$SEARCH" ]; then
    echo "Search tidak boleh kosong."
    exit 1
fi

TMP_FILE=$(mktemp)

cleanup() {
    rm -f "$TMP_FILE"
}

trap cleanup EXIT

# ============================================================
# HEADER
# ============================================================

echo
echo "========================================"
echo " SEARCH DATABASE"
echo "========================================"
echo "Database : $DB"
echo "Search   : $SEARCH"
echo "========================================"
echo

# ============================================================
# CONVERT SEARCH TO HEX
# ============================================================

SEARCH_HEX=$(printf '%s' "$SEARCH" | xxd -p -c 99999)

# ============================================================
# FIND TABLE + COLUMN
# ============================================================

echo "Scanning database..."
echo

mariadb -N -B "$DB" -e "
SELECT TABLE_NAME, COLUMN_NAME
FROM INFORMATION_SCHEMA.COLUMNS
WHERE TABLE_SCHEMA='$DB'
AND DATA_TYPE IN (
    'char',
    'varchar',
    'text',
    'tinytext',
    'mediumtext',
    'longtext'
);
" | while IFS=$'\t' read -r T C; do

    FOUND=$(mariadb -N -B "$DB" -e "
        SELECT 1
        FROM \`$T\`
        WHERE INSTR(
            \`$C\`,
            CONVERT(0x$SEARCH_HEX USING utf8mb4)
        ) > 0
        LIMIT 1;
    " 2>/dev/null)

    if [ "$FOUND" = "1" ]; then
        printf '%s\t%s\n' "$T" "$C" >> "$TMP_FILE"
    fi

done

# ============================================================
# CHECK RESULT
# ============================================================

if [ ! -s "$TMP_FILE" ]; then
    echo
    echo "Tidak ditemukan."
    exit 0
fi

echo
echo "Ditemukan:"
echo

INDEX=1

while IFS=$'\t' read -r T C; do
    printf " [%d] %s.%s\n" "$INDEX" "$T" "$C"
    INDEX=$((INDEX + 1))
done < "$TMP_FILE"

# ============================================================
# SELECT TABLE + COLUMN
# ============================================================

echo
echo "========================================"

read -rp "Pilih nomor yang ingin di-replace: " CHOICE

if ! [[ "$CHOICE" =~ ^[0-9]+$ ]]; then
    echo "Pilihan tidak valid."
    exit 1
fi

SELECTED=$(sed -n "${CHOICE}p" "$TMP_FILE")

if [ -z "$SELECTED" ]; then
    echo "Nomor tidak ditemukan."
    exit 1
fi

T=$(printf '%s' "$SELECTED" | cut -f1)
C=$(printf '%s' "$SELECTED" | cut -f2)

# ============================================================
# SELECTED INFO
# ============================================================

echo
echo "========================================"
echo " SELECTED"
echo "========================================"
echo "Table  : $T"
echo "Column : $C"
echo "Search : $SEARCH"
echo "========================================"

# ============================================================
# SHOW MATCHING DATA
# ============================================================

echo
echo "Data yang mengandung string tersebut:"
echo

mariadb -N -B "$DB" -e "
SELECT \`$C\`
FROM \`$T\`
WHERE INSTR(
    \`$C\`,
    CONVERT(0x$SEARCH_HEX USING utf8mb4)
) > 0;
"

# ============================================================
# INPUT REPLACEMENT
# ============================================================

echo
echo "========================================"

read -rp "Replace dengan (kosong = hapus): " REPLACE_WITH

# ============================================================
# CONVERT REPLACEMENT TO SQL
# ============================================================

if [ -z "$REPLACE_WITH" ]; then

    # Replacement kosong = hapus string
    REPLACE_SQL="''"

else

    REPLACE_HEX=$(printf '%s' "$REPLACE_WITH" | xxd -p -c 99999)

    REPLACE_SQL="CONVERT(0x${REPLACE_HEX} USING utf8mb4)"

fi

# ============================================================
# PREVIEW
# ============================================================

echo
echo "========================================"
echo " PREVIEW"
echo "========================================"
echo
echo "Table   : $T"
echo "Column  : $C"
echo
echo "Find:"
echo "$SEARCH"
echo
echo "Replace:"
if [ -z "$REPLACE_WITH" ]; then
    echo "(KOSONG / HAPUS)"
else
    echo "$REPLACE_WITH"
fi
echo
echo "========================================"

echo
echo "Contoh hasil:"
echo

mariadb -N -B "$DB" -e "
SELECT REPLACE(
    \`$C\`,
    CONVERT(0x$SEARCH_HEX USING utf8mb4),
    $REPLACE_SQL
)
FROM \`$T\`
WHERE INSTR(
    \`$C\`,
    CONVERT(0x$SEARCH_HEX USING utf8mb4)
) > 0
LIMIT 5;
"

# ============================================================
# CONFIRM
# ============================================================

echo
echo "========================================"

read -rp "Lanjutkan replacement? [y/N]: " CONFIRM

if [[ ! "$CONFIRM" =~ ^[Yy]$ ]]; then
    echo
    echo "Dibatalkan."
    exit 0
fi

# ============================================================
# UPDATE
# ============================================================

echo
echo "Melakukan replacement..."

mariadb "$DB" -e "
UPDATE \`$T\`
SET \`$C\` = REPLACE(
    \`$C\`,
    CONVERT(0x$SEARCH_HEX USING utf8mb4),
    $REPLACE_SQL
)
WHERE INSTR(
    \`$C\`,
    CONVERT(0x$SEARCH_HEX USING utf8mb4)
) > 0;
"

# ============================================================
# DONE
# ============================================================

echo
echo "========================================"
echo " SELESAI"
echo "========================================"
echo "Database : $DB"
echo "Table    : $T"
echo "Column   : $C"
echo "Find     : $SEARCH"

if [ -z "$REPLACE_WITH" ]; then
    echo "Replace  : (KOSONG / DIHAPUS)"
else
    echo "Replace  : $REPLACE_WITH"
fi

echo "========================================"
