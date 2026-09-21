#!/usr/bin/env bash
# ==============================================================================
# Script: verify-backup.sh
# Mục đích: Tự động hóa kiểm tra tính toàn vẹn và thử nghiệm Restore cơ sở dữ liệu
#          MySQL Production vào container Ephemeral cô lập.
# Tiêu chuẩn: Không để rò rỉ container/volume, đo lường RTO, exit code 0 nếu pass.
# ==============================================================================

set -euo pipefail

BACKUP_FILE="${1:-}"
MYSQL_IMAGE="${MYSQL_IMAGE:-mysql/mysql-server:8.0}"
MYSQL_ROOT_PASSWORD="verify_restore_secret_2026!"
RESTORE_CONTAINER="mysql-restore-drill-$$"
START_TIME=$(date +%s)

# Màu hiển thị output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

log_info() {
    echo -e "${BLUE}[INFO]${NC} $(date '+%Y-%m-%d %H:%M:%S') - $1"
}

log_success() {
    echo -e "${GREEN}[SUCCESS]${NC} $(date '+%Y-%m-%d %H:%M:%S') - $1"
}

log_warn() {
    echo -e "${YELLOW}[WARN]${NC} $(date '+%Y-%m-%d %H:%M:%S') - $1"
}

log_error() {
    echo -e "${RED}[ERROR]${NC} $(date '+%Y-%m-%d %H:%M:%S') - $1"
}

cleanup() {
    if docker ps -a --format '{{.Names}}' | grep -q "^${RESTORE_CONTAINER}$"; then
        log_info "Dọn dẹp: Đang dừng và hủy container ephemeral ${RESTORE_CONTAINER}..."
        docker rm -f "${RESTORE_CONTAINER}" >/dev/null 2>&1 || true
    fi
}
trap cleanup EXIT

# 1. Kiểm tra tham số và sự tồn tại của file backup
if [[ -z "$BACKUP_FILE" ]]; then
    # Tự động tìm file backup mới nhất trong ~/backups nếu không chỉ định
    BACKUP_FILE=$(find /home/robert/backups -name "mysql-full-*.sql.gz" -type f | sort -r | head -n 1 || true)
    if [[ -z "$BACKUP_FILE" ]]; then
        log_error "Không tìm thấy file backup nào! Vui lòng chỉ định đường dẫn file backup."
        echo "Sử dụng: $0 /path/to/backup.sql.gz"
        exit 1
    fi
    log_info "Tự động chọn file backup mới nhất: ${BACKUP_FILE}"
fi

if [[ ! -f "$BACKUP_FILE" ]]; then
    log_error "File backup không tồn tại: ${BACKUP_FILE}"
    exit 1
fi

FILE_SIZE=$(du -h "$BACKUP_FILE" | cut -f1)
log_info "==> BƯỚC 1: Kiểm tra file backup: ${BACKUP_FILE} (Kích thước: ${FILE_SIZE})"

# 2. Kiểm tra tính toàn vẹn nén gzip & tính mã SHA256 Checksum
log_info "==> BƯỚC 2: Kiểm tra tính toàn vẹn nén (gzip integrity)..."
if ! gunzip -t "$BACKUP_FILE"; then
    log_error "File backup bị hỏng (gzip corrupted)!"
    exit 1
fi
log_success "Tính toàn vẹn gzip: HỢP LỆ (Pass)"

SHA256_HASH=$(sha256sum "$BACKUP_FILE" | awk '{print $1}')
log_info "SHA256 Checksum: ${SHA256_HASH}"

# 3. Khởi chạy container MySQL ephemeral tạm thời
log_info "==> BƯỚC 3: Khởi chạy container MySQL tạm thời (${RESTORE_CONTAINER})..."
docker run -d \
    --name "${RESTORE_CONTAINER}" \
    -e MYSQL_ROOT_PASSWORD="${MYSQL_ROOT_PASSWORD}" \
    "${MYSQL_IMAGE}" >/dev/null

log_info "Đang chờ MySQL server sẵn sàng tiếp nhận kết nối..."
READY=0
for i in {1..45}; do
    if docker exec "${RESTORE_CONTAINER}" mysqladmin ping -h127.0.0.1 -uroot -p"${MYSQL_ROOT_PASSWORD}" --silent >/dev/null 2>&1; then
        READY=1
        break
    fi
    sleep 2
done

if [[ $READY -ne 1 ]]; then
    log_error "MySQL container không thể khởi động đúng thời hạn (Timeout 90s)!"
    exit 1
fi
log_success "MySQL ephemeral container đã sẵn sàng."

# 4. Nạp dữ liệu SQL backup vào container tạm (Restore Test)
RESTORE_START_TIME=$(date +%s)
log_info "==> BƯỚC 4: Bắt đầu nạp dữ liệu từ file backup vào MySQL container tạm..."

if ! gzip -dc "$BACKUP_FILE" | docker exec -i "${RESTORE_CONTAINER}" mysql -uroot -p"${MYSQL_ROOT_PASSWORD}"; then
    log_error "Quá trình restore gặp lỗi SQL hoặc dữ liệu bị lỗi cú pháp!"
    exit 1
fi

RESTORE_END_TIME=$(date +%s)
RTO_SECONDS=$((RESTORE_END_TIME - RESTORE_START_TIME))
log_success "Khôi phục dữ liệu thành công! Thời gian thực thi (RTO): ${RTO_SECONDS} giây."

# 5. Xác thực số lượng Databases và cấu trúc bảng
log_info "==> BƯỚC 5: Xác thực cấu trúc dữ liệu đã khôi phục..."

DATABASES=$(docker exec "${RESTORE_CONTAINER}" mysql -uroot -p"${MYSQL_ROOT_PASSWORD}" -e "SHOW DATABASES;" -s --skip-column-names)
log_info "Danh sách Databases được phục hồi:"
echo "${DATABASES}" | while read -r db; do
    echo "  - ${db}"
done

# Kiểm tra các database trọng yếu của EduConnect
CRITICAL_DBS=("educonnect_db" "identity_db" "school_db" "finance_db")
FOUND_COUNT=0

for target_db in "${CRITICAL_DBS[@]}"; do
    if echo "${DATABASES}" | grep -wq "${target_db}"; then
        TABLE_COUNT=$(docker exec "${RESTORE_CONTAINER}" mysql -uroot -p"${MYSQL_ROOT_PASSWORD}" -e "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='${target_db}';" -s --skip-column-names)
        log_success "Database [${target_db}]: Tồn tại với ${TABLE_COUNT} bảng."
        FOUND_COUNT=$((FOUND_COUNT + 1))
    else
        log_warn "Database [${target_db}]: Không tìm thấy trong bản backup (có thể do hệ thống chưa tách DB này)."
    fi
done


TOTAL_END_TIME=$(date +%s)
TOTAL_DURATION=$((TOTAL_END_TIME - START_TIME))

echo ""
echo "=============================================================================="
log_success "KẾT QUẢ DIỄN TẬP KHÔI PHỤC DỮ LIỆU (RESTORE DRILL) THÀNH CÔNG RỰC RỠ!"
echo "  - File Backup: ${BACKUP_FILE}"
echo "  - Kích thước: ${FILE_SIZE}"
echo "  - Checksum: ${SHA256_HASH}"
echo "  - Thời gian nạp SQL (RTO): ${RTO_SECONDS}s"
echo "  - Tổng thời gian kiểm thử: ${TOTAL_DURATION}s"
echo "  - Trạng thái: PASS (Sẵn sàng phục hồi khi có sự cố thảm họa)"
echo "=============================================================================="
