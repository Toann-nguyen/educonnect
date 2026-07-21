#!/bin/bash
# ETL: Export school tables from monolith educonnect_db to school_db
set -e

MYSQL_PW="KietTacBaoMat2026!"
SRC_DB="educonnect_db"
DST_DB="school_db"
MYSQL_CMD="docker exec -i mysql_db mysql -uroot -p${MYSQL_PW}"
MYSQL_DUMP="docker exec mysql_db mysqldump -uroot -p${MYSQL_PW}"

ACTUAL_TABLES="academic_years attendances classes discipline_actions discipline_appeals discipline_types disciplines events event_registrations grades library_books library_transactions schedules student_conduct_scores student_guardians students subjects"

# Map from model names to actual table names
declare -A TABLE_MAP
TABLE_MAP["academic_years"]="academic_years"
TABLE_MAP["attendances"]="attendances"
TABLE_MAP["classes"]="classes"
TABLE_MAP["discipline_actions"]="discipline_actions"
TABLE_MAP["discipline_appeals"]="discipline_appeals"
TABLE_MAP["discipline_types"]="discipline_types"
TABLE_MAP["disciplines"]="disciplines"
TABLE_MAP["events"]="events"
TABLE_MAP["event_registrations"]="event_registrations"
TABLE_MAP["grades"]="grades"
TABLE_MAP["library_books"]="library_books"
TABLE_MAP["library_transactions"]="library_transactions"
TABLE_MAP["schedules"]="schedules"
TABLE_MAP["student_conduct_scores"]="student_conduct_scores"
TABLE_MAP["student_guardians"]="student_guardians"
TABLE_MAP["students"]="students"
TABLE_MAP["subjects"]="subjects"

echo "=== ETL School Data: ${SRC_DB} -> ${DST_DB} ==="
echo ""

for key in "${!TABLE_MAP[@]}"; do
    table="${TABLE_MAP[$key]}"
    echo -n "Checking ${SRC_DB}.${table}... "
    EXISTS=$(${MYSQL_CMD} -N -e "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='${SRC_DB}' AND table_name='${table}';" 2>/dev/null)
    if [ "$EXISTS" = "1" ]; then
        ROWS=$(${MYSQL_CMD} -N -e "SELECT COUNT(*) FROM ${SRC_DB}.${table};" 2>/dev/null)
        echo "found (${ROWS} rows), copying..."
        # Disable FK checks, dump table structure + data, re-enable FK checks
        ${MYSQL_CMD} -N -e "SET FOREIGN_KEY_CHECKS=0;" ${DST_DB} 2>/dev/null
        ${MYSQL_DUMP} --add-drop-table --complete-insert --create-options ${SRC_DB} ${table} 2>/dev/null | \
            sed "s/\`${SRC_DB}\`/\`${DST_DB}\`/g" | \
            ${MYSQL_CMD} ${DST_DB} 2>/dev/null
        ${MYSQL_CMD} -N -e "SET FOREIGN_KEY_CHECKS=1;" ${DST_DB} 2>/dev/null
        echo "  -> Done"
    else
        echo "not found, skipping"
    fi
done

echo ""
echo "=== Verification ==="
for key in "${!TABLE_MAP[@]}"; do
    table="${TABLE_MAP[$key]}"
    SRC_COUNT=$(${MYSQL_CMD} -N -e "SELECT COUNT(*) FROM ${SRC_DB}.${table};" 2>/dev/null || echo "0")
    DST_COUNT=$(${MYSQL_CMD} -N -e "SELECT COUNT(*) FROM ${DST_DB}.${table};" 2>/dev/null || echo "0")
    echo "${table}: ${SRC_DB}=${SRC_COUNT}, ${DST_DB}=${DST_COUNT}"
done

echo ""
echo "=== ETL Complete ==="
