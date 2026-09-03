# Microservice Migration — A → B

## Trạng thái trước (Hybrid)
- Infra: `docker-compose.yml` đã microservice-split (gateway + 5 service) nhưng DB chung 1 MySQL 3 database
- Code: `app/Domains/Finance` và `app/Domains/Identity` vẫn nằm trong monolith → cross-DB Eloquent, duplicate với `educonnect-identity` và `services/finance` (Go)

## Đã làm (A — Dọn monolith)
- **Finance**: Xoá `app/Domains/Finance/Models, Repositories, Policies, Http, Exceptions, Services/*` chỉ giữ `FinanceApiClient.php`; xoá `app/Http/Resources/{FeeType,Invoice,Payment}Resource` và `Requests` finance; `DashBoardService` chuyển từ `Payment::sum()` / `Invoice::count()` sang `FinanceApiClient::getDashboardFinancials()` (fallback 0 nếu Go chưa có /stats)
- **Identity**: Giữ `Models/* + Middleware/JwtMiddleware + Services/PermissionCacheService + Support/RequestIp`; xoá `Controllers, Repositories, Services/Auth*, Jobs, Mail, Events, Policies, Exceptions`; `RouteServiceProvider` bỏ load `routes/identity.php` (gateway route `/api/auth/*` → `identity:9000`)
- **Providers**: `AppServiceProvider` bỏ bind Finance (Invoice/Payment/FeeType) trong monolith
- Backup: `/home/robert/backups/educonnect-pre-microservice-20260903/`

## Đã làm (B1 — DB per service)
- `docker-compose.yml`: Tách 1 `mysql` → 3 service `mysql-identity:3308`, `mysql-school:3309`, `mysql-finance:3310` (mỗi volume riêng `mysql_*_data`), legacy `mysql` giữ với `profiles: ["legacy"]` để migrate
- `docker/mysql/{identity,school,finance}-init/01-*.sh` tạo DB + user riêng
- `php` → `mysql-school`, `identity` → `mysql-identity`, `finance` → `mysql-finance`
- Validate: `docker compose config` OK, `php artisan route:list` OK (104 routes, 0 `api/auth` trong school), services `Up (healthy)`

## Còn lại (B2 — Tách repo/deploy)
- Mỗi service đã có Dockerfile riêng và build độc lập → đủ điều kiện tách repo
- Đề xuất tách repo:
  - `educonnect-school` (từ `~/educonnect` sau khi dọn) — chỉ School domain
  - `educonnect-identity` (đã tách sẵn `~/educonnect-identity`)
  - `educonnect-finance` (`services/finance`), `educonnect-notify`, `educonnect-realtime`
- `docker-compose.prod.yml` hiện vẫn 1 mysql — cần áp dụng tương tự B1 khi lên prod (hoặc dùng managed DB riêng per service)
- Gateway `nginx/gateway.conf.template` đã route đúng, giữ nguyên
- Next: `docker compose up -d --build` với 3 DB mới → chạy migrate từng service → dump/restore data từ legacy `mysql` nếu cần → sau verify xoá legacy profile

## Cách chạy sau migration
```bash
# Dev với 3 DB riêng
docker compose up -d --build
docker compose logs -f php identity finance

# Check DB per service
docker exec educonnect-dev-mysql-identity-1 mysql -uidentity_user -p$IDENTITY_DB_PASSWORD -e "SHOW TABLES" identity_db
docker exec educonnect-dev-mysql-school-1 mysql -uschool_user -p$SCHOOL_DB_PASSWORD -e "SHOW TABLES" school_db
docker exec educonnect-dev-mysql-finance-1 mysql -ufinance_user -p$FINANCE_DB_PASSWORD -e "SHOW TABLES" finance_db

# Legacy migrate (nếu cần)
docker compose --profile legacy up -d mysql
docker exec educonnect-dev-mysql-1 mysqldump -uroot -p$MYSQL_ROOT_PASSWORD identity_db | docker exec -i educonnect-dev-mysql-identity-1 mysql -uidentity_user -p$IDENTITY_DB_PASSWORD identity_db
```

## Loop tiếp theo
- Thêm endpoint `GET /api/finance/stats` trong Go finance để `FinanceApiClient` lấy revenue thực
- Xóa hẳn code legacy `AppServiceProvider` imports `App\Repositories\*` và `Identity` bindings chết
- Tách `.env.example` thêm `FORWARD_*_DB_PORT` và `DB_*_HOST` per service
