# database/migrations/finance — DB per Service (Finance)

> Phase 1 B1 — tách `finance_db` riêng (`mysql-finance:3310`), mono-repo `~/educonnect`. Không đụng `/home/robert/production`.

## Nguồn gốc
6 file hiện còn ở `database/migrations/` root phải move vào đây (đã có checklist `docs/microservices/phase-1-db-per-service-checklist.md:1a-1`):

```
2025_09_30_022348_create_fee_types_table.php
2025_09_30_022405_create_invoices_table.php
2025_09_30_022424_create_invoice_items_table.php
2025_09_30_022448_create_invoice_fee_types_table.php
2025_09_30_022506_create_payments_table.php
2025_10_01_000407_add_soft_deletes_to_invoice_items_table.php
```

Giữ `identity/` (18 files) + `school/` (21 files) đã tách.

## Cách move (1 lệnh)

```bash
mkdir -p database/migrations/finance
git mv database/migrations/2025_09_30_022348_create_fee_types_table.php        database/migrations/finance/
git mv database/migrations/2025_09_30_022405_create_invoices_table.php         database/migrations/finance/
git mv database/migrations/2025_09_30_022424_create_invoice_items_table.php    database/migrations/finance/
git mv database/migrations/2025_09_30_022448_create_invoice_fee_types_table.php database/migrations/finance/
git mv database/migrations/2025_09_30_022506_create_payments_table.php         database/migrations/finance/
git mv database/migrations/2025_10_01_000407_add_soft_deletes_to_invoice_items_table.php database/migrations/finance/
git status --short
```

## Chạy migration per-service (tuân docs/db-rules.md)

### Local / dev (3 MySQL riêng)

```bash
docker compose up -d mysql-identity mysql-school mysql-finance

# Verify trước khi migrate — chỉ SELECT count, không DROP/DELETE
docker exec educonnect-dev-mysql-finance-1 mysql -ufinance_user -p$FINANCE_DB_PASSWORD -e "SELECT count(*) FROM information_schema.tables WHERE table_schema='finance_db';"

php artisan migrate --database=finance --path=database/migrations/finance
php artisan migrate:status --database=finance --path=database/migrations/finance

# Identity & School (đã OK)
php artisan migrate --database=identity --path=database/migrations/identity
php artisan migrate --database=school   --path=database/migrations/school
```

### Lưu ý connection

- Finance migrations hiện dùng `Schema::create` (không `Schema::connection('finance')`). Khi chạy với `--database=finance`, Laravel sẽ dùng `finance` làm default connection cho run đó → bảng sẽ tạo đúng trong `finance_db`. Để tường minh hơn có thể đổi thành `Schema::connection('finance')->create(...)` (optional, không bắt buộc).
- `config/database.php:finance` phải có `host => env('DB_FINANCE_HOST', env('DB_HOST','mysql-finance'))`. Hiện default là `'mysql'` (legacy profile) → sửa hoặc export `DB_FINANCE_HOST=mysql-finance` khi chạy artisan ngoài Docker.
- Go finance `services/finance/main.go:194` dùng `gorm AutoMigrate(&FeeType{}, &Invoice{}, &Payment{}, &UserReadModel{})` trực tiếp vào `finance_db`. Schema Go hiện drift với PHP (Go thiếu `invoice_number`, `paid_amount`...). Coi `internal/model/models.go` đăng ký, thống nhất trước khi Go thành canonical (`03-domain-architecture.md:158`).

## Verify sau move

```bash
ls -1 database/migrations/*.php 2>&1 | grep -E "fee|invoice|payment" && echo "⚠️ còn file ở root" || echo "✅ root cleaned"
ls -1 database/migrations/finance/  # expect 6

docker compose config --quiet && echo "compose OK"
php artisan migrate:status --database=finance --path=database/migrations/finance
docker exec educonnect-dev-mysql-finance-1 mysql -ufinance_user -p$FINANCE_DB_PASSWORD -e "SHOW TABLES FROM finance_db;"
```

## Rollback (local only)

```bash
php artisan migrate:rollback --database=finance --path=database/migrations/finance --step=1
# Hoặc fresh (CHỈ local, tuân db-rules: không chạy trên prod khi chưa confirm)
php artisan migrate:fresh --database=finance --path=database/migrations/finance
```

## Liên quan

- Plan tổng: `docs/microservices/plan-chore-infra-devops-grpc-cqrs.md:Phase 1a`
- Boundary: `docs/architecture/03-domain-architecture.md:151 Finance`
- ETL legacy: `scripts/etl-legacy-mysql-draft.sh --dry-run`
- Checklist: `docs/microservices/phase-1-db-per-service-checklist.md`
