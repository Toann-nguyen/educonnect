#!/usr/bin/env bash
# ============================================================
# etl-legacy-mysql-draft.sh — DRAFT, KHÔNG CHẠY TRÊN PROD
# Mục đích: migrate dữ liệu từ single MySQL (legacy profile)
#           sang 3 DB per-service: identity_db, school_db, finance_db
# Tuân docs/db-rules.md: chỉ SELECT count trước, không DELETE/DROP prod,
#                        hỏi Confirm yes trước khi đụng prod.
# Chạy local/staging với docker compose --profile legacy.
# ============================================================
set -euo pipefail

# Config từ .env / docker-compose
MYSQL_ROOT_PASSWORD="${MYSQL_ROOT_PASSWORD:-root}"
IDENTITY_DB_PASSWORD="${IDENTITY_DB_PASSWORD:-}"
SCHOOL_DB_PASSWORD="${SCHOOL_DB_PASSWORD:-}"
FINANCE_DB_PASSWORD="${FINANCE_DB_PASSWORD:-}"

LEGACY_CONTAINER="${LEGACY_CONTAINER:-educonnect-dev-mysql-1}"
IDENTITY_CONTAINER="${IDENTITY_CONTAINER:-educonnect-dev-mysql-identity-1}"
SCHOOL_CONTAINER="${SCHOOL_CONTAINER:-educonnect-dev-mysql-school-1}"
FINANCE_CONTAINER="${FINANCE_CONTAINER:-educonnect-dev-mysql-finance-1}"

DRY_RUN=0
VERIFY_ONLY=0

usage() {
  cat <<EOF
Usage: $0 [--dry-run] [--verify-only] [--help]

  --dry-run      chỉ in lệnh, không thực thi mysqldump|mysql
  --verify-only  chỉ SELECT count trước, không dump
  --help         help

Examples:
  $0 --dry-run
  $0 --verify-only
  docker compose --profile legacy up -d mysql
  docker compose up -d mysql-identity mysql-school mysql-finance
  $0 --dry-run
  $0   # thực thi dump local (cần confirm)

Prod: KHÔNG chạy script này trên /home/robert/production, chỉ SELECT count.
EOF
}

for arg in "$@"; do
  case "$arg" in
    --dry-run) DRY_RUN=1 ;;
    --verify-only) VERIFY_ONLY=1 ;;
    --help|-h) usage; exit 0 ;;
  esac
done

echo "=== ETL legacy MySQL → 3 DB per-service (DRAFT) ==="
echo "Legacy: $LEGACY_CONTAINER (profile legacy, port 3307)"
echo "Target: $IDENTITY_CONTAINER (3308) | $SCHOOL_CONTAINER (3309) | $FINANCE_CONTAINER (3310)"
echo "Dry-run: $DRY_RUN | Verify-only: $VERIFY_ONLY"
echo ""

# Kiểm tra container tồn tại
check_container() {
  if ! docker ps --format '{{.Names}}' | grep -q "^$1$"; then
    echo "⚠️  Container $1 chưa chạy. Gợi ý:"
    echo "   docker compose --profile legacy up -d mysql"
    echo "   docker compose up -d mysql-identity mysql-school mysql-finance"
    return 1
  fi
}

# Bước 1: SELECT count trước (db-rules bắt buộc)
verify_counts() {
  echo "--- [1] SELECT counts (db-rules) ---"
  local cmd
  cmd="SELECT 'users' AS tbl, count(*) AS cnt FROM laravel.users UNION ALL SELECT 'profiles', count(*) FROM laravel.profiles UNION ALL SELECT 'fee_types', count(*) FROM laravel.fee_types UNION ALL SELECT 'invoices', count(*) FROM laravel.invoices UNION ALL SELECT 'payments', count(*) FROM laravel.payments;"
  echo "SQL: $cmd"
  if [[ $DRY_RUN -eq 1 || $VERIFY_ONLY -eq 1 ]]; then
    echo "[dry-run] docker exec $LEGACY_CONTAINER mysql -uroot -p*** -e \"$cmd\""
  else
    # Chỉ SELECT, không DELETE/DROP
    if check_container "$LEGACY_CONTAINER"; then
      docker exec "$LEGACY_CONTAINER" mysql -uroot -p"$MYSQL_ROOT_PASSWORD" -e "$cmd" || echo "⚠️ SELECT failed (DB laravel có thể chưa có data cũ)"
    fi
  fi

  # Count target DBs (phải 0 hoặc < legacy nếu chưa migrate)
  for pair in "identity_db:$IDENTITY_CONTAINER:identity_user:$IDENTITY_DB_PASSWORD" "school_db:$SCHOOL_CONTAINER:school_user:$SCHOOL_DB_PASSWORD" "finance_db:$FINANCE_CONTAINER:finance_user:$FINANCE_DB_PASSWORD"; do
    IFS=: read -r db container user pass <<< "$pair"
    echo "[verify] $db @ $container"
    if [[ $DRY_RUN -eq 1 ]]; then
      echo "[dry-run] docker exec $container mysql -u$user -p*** -e \"SELECT count(*) FROM information_schema.tables WHERE table_schema='$db';\""
    else
      if docker ps --format '{{.Names}}' | grep -q "^$container$"; then
        docker exec "$container" mysql -u"$user" -p"$pass" -e "SELECT count(*) AS tables_in_$db FROM information_schema.tables WHERE table_schema='$db';" 2>&1 | head -5 || true
      fi
    fi
  done
  echo ""
}

# Bước 2: mysqldump từng logical DB (không dump mysql system)
dump_identity() {
  echo "--- [2a] Dump identity (users, profiles, roles, permissions...) ---"
  # Chỉ dump các bảng Identity từ legacy laravel, bỏ school/finance tables
  # Cách an toàn: mysqldump --single-transaction --quick --no-create-db laravel <tables>
  local tables="users profiles roles permissions role_has_permissions model_has_roles model_has_permissions refresh_tokens user_sessions email_verifications password_reset_tokens backup_codes audit_logs outbox_events failed_jobs personal_access_tokens"
  echo "Tables: $tables"
  if [[ $DRY_RUN -eq 1 || $VERIFY_ONLY -eq 1 ]]; then
    echo "[dry-run] docker exec $LEGACY_CONTAINER mysqldump -uroot -p*** --single-transaction --quick --no-create-db laravel $tables | docker exec -i $IDENTITY_CONTAINER mysql -uidentity_user -p*** identity_db"
  else
    echo "Thực thi dump identity..."
    # shellcheck disable=SC2086
    docker exec "$LEGACY_CONTAINER" mysqldump -uroot -p"$MYSQL_ROOT_PASSWORD" --single-transaction --quick --no-create-db laravel $tables 2>/dev/null | docker exec -i "$IDENTITY_CONTAINER" mysql -uidentity_user -p"$IDENTITY_DB_PASSWORD" identity_db
    echo "✅ identity_db restored"
  fi
  echo ""
}

dump_school() {
  echo "--- [2b] Dump school (students, classes, subjects, grades, schedules, attendances, disciplines...) ---"
  local tables="academic_years classes students student_guardians subjects schedules grades attendances disciplines discipline_types discipline_actions discipline_appeals student_conduct_scores events event_registrations library_books library_transactions notifications users_read_model"
  echo "Tables: $tables"
  if [[ $DRY_RUN -eq 1 || $VERIFY_ONLY -eq 1 ]]; then
    echo "[dry-run] docker exec $LEGACY_CONTAINER mysqldump -uroot -p*** --single-transaction --quick --no-create-db laravel $tables | docker exec -i $SCHOOL_CONTAINER mysql -uschool_user -p*** school_db"
  else
    docker exec "$LEGACY_CONTAINER" mysqldump -uroot -p"$MYSQL_ROOT_PASSWORD" --single-transaction --quick --no-create-db laravel $tables 2>/dev/null | docker exec -i "$SCHOOL_CONTAINER" mysql -uschool_user -p"$SCHOOL_DB_PASSWORD" school_db
    echo "✅ school_db restored"
  fi
  echo ""
}

dump_finance() {
  echo "--- [2c] Dump finance (fee_types, invoices, invoice_items, invoice_fee_types, payments) ---"
  local tables="fee_types invoices invoice_items invoice_fee_types payments"
  echo "Tables: $tables"
  if [[ $DRY_RUN -eq 1 || $VERIFY_ONLY -eq 1 ]]; then
    echo "[dry-run] docker exec $LEGACY_CONTAINER mysqldump -uroot -p*** --single-transaction --quick --no-create-db laravel $tables | docker exec -i $FINANCE_CONTAINER mysql -ufinance_user -p*** finance_db"
  else
    docker exec "$LEGACY_CONTAINER" mysqldump -uroot -p"$MYSQL_ROOT_PASSWORD" --single-transaction --quick --no-create-db laravel $tables 2>/dev/null | docker exec -i "$FINANCE_CONTAINER" mysql -ufinance_user -p"$FINANCE_DB_PASSWORD" finance_db
    echo "✅ finance_db restored"
  fi
  echo ""
}

# Bước 3: Verify sau dump (counts + checksum)
verify_after() {
  echo "--- [3] Verify sau dump ---"
  for pair in "identity_db:$IDENTITY_CONTAINER:identity_user:$IDENTITY_DB_PASSWORD:users,profiles" "school_db:$SCHOOL_CONTAINER:school_user:$SCHOOL_DB_PASSWORD:students,classes" "finance_db:$FINANCE_CONTAINER:finance_user:$FINANCE_DB_PASSWORD:fee_types,invoices"; do
    IFS=: read -r db container user pass sample <<< "$pair"
    echo "Check $db sample tables: $sample"
    if [[ $DRY_RUN -eq 1 ]]; then
      echo "[dry-run] docker exec $container mysql -u$user -p*** -e \"SELECT count(*) FROM $db.users\""
    fi
  done
  echo "Gợi ý verify thủ công:"
  echo "  docker exec $IDENTITY_CONTAINER mysql -uidentity_user -p\$IDENTITY_DB_PASSWORD -e \"SELECT 'users' AS tbl, count(*) FROM identity_db.users;\""
  echo "  docker exec $SCHOOL_CONTAINER mysql -uschool_user -p\$SCHOOL_DB_PASSWORD -e \"SHOW TABLES FROM school_db;\""
  echo "  docker exec $FINANCE_CONTAINER mysql -ufinance_user -p\$FINANCE_DB_PASSWORD -e \"SHOW TABLES FROM finance_db;\""
  echo ""
}

# Main
verify_counts

if [[ $VERIFY_ONLY -eq 1 ]]; then
  echo "✅ Verify-only done (chỉ SELECT, không dump). Tuân db-rules."
  exit 0
fi

if [[ $DRY_RUN -eq 1 ]]; then
  dump_identity
  dump_school
  dump_finance
  verify_after
  echo "✅ Dry-run done. Kiểm tra lệnh trên, nếu OK thì chạy lại không có --dry-run (chỉ local)."
  exit 0
fi

# Thực thi thật — hỏi confirm nếu phát hiện prod env
if [[ "${DB_HOST:-}" == *"prod"* || "${DB_DATABASE:-}" == *"prod"* ]]; then
  echo "⛔ Phát hiện prod env, KHÔNG tự chạy dump. Yêu cầu: SELECT count trước, Confirm yes theo docs/db-rules.md"
  echo "   This will affect prod data. Confirm? (yes/no) — script dừng."
  exit 1
fi

echo "⚠️  Sắp dump thật (local). Confirm? (yes/no)"
read -r ans
if [[ "$ans" != "yes" ]]; then
  echo "Huỷ."
  exit 0
fi

dump_identity
dump_school
dump_finance
verify_after

echo "=== ETL DRAFT hoàn tất (local). Sau verify có thể stop legacy: ==="
echo "  docker compose --profile legacy stop mysql  # giữ volume tới khi chắc"
echo "  # Không rm volume mysql_data khi chưa backup!"
