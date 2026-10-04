DB="thecosmic"
SEARCH='http://192.168.100.11:8000'

mariadb -N -B "$DB" -e "
SELECT TABLE_NAME, COLUMN_NAME
FROM INFORMATION_SCHEMA.COLUMNS
WHERE TABLE_SCHEMA='$DB'
  AND DATA_TYPE IN ('char','varchar','text','tinytext','mediumtext','longtext');
" | while IFS=$'\t' read -r T C; do

    R=$(mariadb -N -B "$DB" -e "
        SELECT \`$C\`
        FROM \`$T\`
        WHERE \`$C\` LIKE '%$SEARCH%';
    " 2>/dev/null)

    if [ -n "$R" ]; then
        echo
        echo "========================================"
        echo "TABLE  : $T"
        echo "COLUMN : $C"
        echo "========================================"
        echo "$R"
    fi

done
