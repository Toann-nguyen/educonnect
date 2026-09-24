# Phase 1 — Checklist DB per Service (B1 Hoàn Thiện) — chore/infra-devops

Branch: `chore/infra-devops` (HEAD 8d3b9a1), rebase `origin/main`. Scope: chỉ trong `~/educonnect`, KHÔNG đụng `/home/robert/production`.

> Kế thừa `docs/microservices/plan-chore-infra-devops-grpc-cqrs.md:Phase 1` + `MICROSERVICE-MIGRATION.md` + `docs/microservices/task_2_database_split.md`. Laya `plan 0.77/conf 0.47, risk 0.79` → làm từng bước, verify kỹ.

---

## 0. Root cause còn thiếu gì (Evidence 2026-09-24)

| # | Vấn đề | Evidence | Ảnh hưởng |
|---|--------|----------|-----------|
| **R1** | **Finance migrations còn ở root** | `database/migrations/: 6 file` `2025_09_30_*fee_types|invoices|invoice_items|invoice_fee_types|payments` + `2025_10_01_*add_soft_deletes_invoice_items` chưa move vào `database/migrations/finance/` trong khi `identity/` (18 files) + `school/` (21 files) đã tách | `php artisan migrate --database=finance --path=database/migrations/finance` không có path; `migrate --database=mysql` sẽ tạo nhầm bảng finance vào `school_db` (default `DB_HOST=mysql-school`). Go `AutoMigrate` đã chạy nhưng schema lệch với PHP (xem R1b) |
| **R1b** | **Schema PHP vs Go drift** | PHP `invoices: invoice_number,student_id,issued_by,total_amount,paid_amount,due_date,status` / `invoice_items: invoice_id,fee_type_id,description,unit_price,quantity,total_amount` / `payments: payer_user_id,created_by_user_id,payment_method` vs Go `internal/model/models.go:15 Invoice{StudentID,FeeTypeID,Amount,Status,DueDate,PaidAt}` thiếu `invoice_number`/`paid_amount` | Finance canonical theo `03-domain-architecture.md:158 Invoice|Payment|FeeType|InvoiceItem` phải thống nhất trước khi Go thành source of truth |
| **R2** | **`config/database.php:finance` host default sai** | `DB_FINANCE_HOST default 'mysql'` (line 140) trong khi docker-compose `mysql-finance` + đặt `DB_HOST=mysql-school` cho `php`. Identity/School fallback `env(DB_HOST)` còn finance hardcode `'mysql'` → php artisan dùng finance sẽ dial legacy `mysql` (profile `legacy`, không chạy mặc định) → `SQLSTATE[HY000] [2002] Connection refused` | `php artisan migrate --database=finance` fail khi không export `DB_FINANCE_HOST`. Cần đổi về `env('DB_FINANCE_HOST', env('DB_HOST','mysql-finance'))` hoặc document export |
| **R3** | **`app/Domains/School/Models/UsersReadModel.php:13` đã OK nhưng sync chưa hoàn chỉnh** | Model `connection='school' table='users_read_model' id primary fillable [name,email,roles,is_active]` + migration `school/2026_08_02_create_users_read_model_table.php` OK. `app/Console/Commands/ConsumeUserEvents.php` tồn tại, `docker-compose.yml:school-worker 98-118 command consume:user-events queue school_users_sync binding user.#`. Tuy nhiên: (a) missing idempotency Redis SETNX như `ConsumeAuthEvents.php:108`, (b) `declareTopology` không set `x-dead-letter-exchange educonnect.dlx` và `x-queue-type quorum`, (c) finance Go `main.go:103 QueueDeclare finance_users_sync` cũng thiếu DLX/quorum, (d) không có test `php artisan consume:user-events --once` với fake RabbitMQ payload | Event replay hoặc redelivery sẽ upsert lặp; message lỗi sẽ drop thay vì DLQ |
| **R4** | **Outbox Events đã có nhưng chưa verify E2E** | `OutboxEvent.php:9 connection=identity`, migration `identity/2026_09_03_create_outbox_events` OK, `Jobs/PublishUserEventJob.php` + `Observers/UserObserver.php` (created/updated/deleted → outbox + dispatch afterCommit, retry 5 backoff, status pending/published/failed). UserObserver còn publish `auth.events` via `AuthEventPublisher` cho T6.1. Chưa có `php artisan outbox:retry` cron, chưa verify `SELECT count(*) FROM identity_db.outbox_events WHERE status='pending'` trước retry | Nếu RabbitMQ down, outbox sẽ tồn đọng `pending`; không có scheduler → delay |
| **R5** | **ETL legacy `mysql` chưa có script draft** | `docker-compose.yml:253 mysql profiles:["legacy"]` + `docker/mysql/init/01-databases.sh` tạo 4 DB, `finance-init/identity-init/school-init` riêng. Nhưng `MICROSERVICE-MIGRATION.md:42 ETL mysqldump|mysql` chỉ là snippet, chưa có file `scripts/etl-legacy-draft.sh` có SELECT count, dump từng DB, verify checksum, tuân `docs/db-rules.md` (SELECT trước DELETE, không DROP prod) | Dev muốn migrate data cũ từ `laravel` single DB sang 3 DB không có hướng dẫn an toàn |
| **R6** | **Verify commands drift** | `plan: php artisan route:list expect 104` nhưng thực tế `php artisan route:list` = 127 routes (có horizon, jwks, scribe, scramble). `docker compose config` OK nhưng finance `DB_FINANCE_*` env thiếu trong `php` service (chỉ `DB_HOST` school). `phpunit.xml` DB `127.0.0.1:3307 testing` connection refused do không có MySQL nào listen 3307 ngoài compose (forward 3307→legacy mysql, 3308→identity etc) | Test CI fail 41/43 dù code đúng |

---

## 1. Checklist chi tiết Phase 1 (tuân `docs/db-rules.md`)

### 1a. DB per service hoàn thiện — move migrations + declare connection

- [ ] **1a-1 Move finance migrations (6 files)**
  ```bash
  mkdir -p database/migrations/finance
  git mv database/migrations/2025_09_30_022348_create_fee_types_table.php        database/migrations/finance/
  git mv database/migrations/2025_09_30_022405_create_invoices_table.php         database/migrations/finance/
  git mv database/migrations/2025_09_30_022424_create_invoice_items_table.php    database/migrations/finance/
  git mv database/migrations/2025_09_30_022448_create_invoice_fee_types_table.php database/migrations/finance/
  git mv database/migrations/2025_09_30_022506_create_payments_table.php         database/migrations/finance/
  git mv database/migrations/2025_10_01_000407_add_soft_deletes_to_invoice_items_table.php database/migrations/finance/
  # Tạo README: database/migrations/finance/README.md (đã tạo skeleton)
  git status --short
  ```
  **Verify:**
  ```bash
  ls -1 database/migrations/finance/   # expect 6 files
  ls -1 database/migrations/*.php 2>&1 | grep -E "fee|invoice|payment" || echo "root cleaned OK"
  docker compose config --quiet && echo "compose OK"
  ```

- [ ] **1a-2 Chuẩn hoá finance migrations cho per-service run**
  - Thêm `Schema::connection('finance')->create` hoặc giữ `Schema::create` nhưng luôn chạy với `--database=finance --path=database/migrations/finance` (document trong README).
  - Nếu dùng `Schema::connection('finance')`, test:
  ```bash
  php artisan migrate:status --database=finance --path=database/migrations/finance
  php artisan migrate:status --database=identity --path=database/migrations/identity  # 18
  php artisan migrate:status --database=school --path=database/migrations/school      # 21
  ```
  - Tuân db-rules: chỉ `SELECT count(*)` trước, không DROP.

- [ ] **1a-3 Sửa `config/database.php:finance` host fallback**
  ```php
  // Hiện tại line 140:
  'host' => env('DB_FINANCE_HOST', 'mysql'),  // legacy
  // Sửa thành:
  'host' => env('DB_FINANCE_HOST', env('DB_HOST', 'mysql-finance')),
  ```
  Hoặc document trong `.env.example` và `docker-compose.yml:php` thêm:
  ```yaml
  DB_FINANCE_HOST: mysql-finance
  DB_FINANCE_PORT: 3306
  DB_FINANCE_DATABASE: finance_db
  DB_FINANCE_USERNAME: finance_user
  DB_FINANCE_PASSWORD: ${FINANCE_DB_PASSWORD}
  ```
  **Verify:**
  ```bash
  php artisan tinker --execute="print_r(config('database.connections.finance'))"
  ```

- [ ] **1a-4 Khai báo `$connection` (đã đủ, chỉ verify)**
  - Identity 11 models: `User, Profile, Role, Permission, RefreshToken, UserSession, EmailVerification, PasswordResetToken, BackupCode, AuditLog, OutboxEvent` → đã `protected $connection='identity'` (`grep -rn` 11/11 OK)
  - School 18 models: `Student, SchoolClass, Subject, Schedule, Grade, Attendance, Discipline*, StudentConductScore, AcademicYear, Event, Library*` → đã `connection='school'` (18/18 OK, `grep -rn "protected \$connection.*school" app/Domains/School/Models`)
  - Finance: PHP chỉ còn `FinanceApiClient.php` (ACL, không model) → OK, Go `finance/internal/model` dùng `gorm` DSN finance_db trực tiếp (`main.go:180`)

### 1b. Boundary Identity/School/Finance

- [ ] **1b-1 Identity boundary (11 models + Auth)**
  - Models table `identity`: users, profiles, roles, permissions, refresh_tokens, user_sessions, email_verifications, password_reset_tokens, backup_codes, audit_logs, outbox_events, failed_jobs, personal_access_tokens
  - Cross-domain chỉ `user_id` ( `03-domain-architecture.md:175` ), không import School/Finance model
  - Verify: `grep -r "App\\\\Domains\\\\School" app/Domains/Identity --include="*.php"` → 0 hit

- [ ] **1b-2 School boundary (18 models)**
  - Tables `school`: academic_years, classes, students, student_guardians, subjects, schedules, grades, attendances, disciplines, discipline_types, discipline_actions, discipline_appeals, student_conduct_scores, events, event_registrations, library_books, library_transactions, notifications, users_read_model
  - `UsersReadModel` là read-model bóng, không FK tới `identity.users`, sync via RabbitMQ

- [ ] **1b-3 Finance boundary (Go canonical)**
  - PHP `app/Domains/Finance` chỉ giữ `Services/FinanceApiClient.php` (HTTP tới `http://finance:8080`, fallback stats 0) — đã xoá Models/Repositories/Policies per `MICROSERVICE-MIGRATION.md:8`
  - Go `services/finance`: `FeeType, Invoice, Payment, UserReadModel` (table `fee_types, invoices, payments, users_read_model`), `AutoMigrate` trong `main.go:194`
  - Verify drift R1b trước khi coi Go là canonical: so sánh PHP migrations vs Go models, lập issue unify

### 1c. Bật school-worker `consume:user-events → UsersReadModel`

- [ ] **1c-1 Outbox → RabbitMQ (Identity)**
  - `UserObserver:created/updated/deleted` → `OutboxEvent::create pending` → `PublishUserEventJob::dispatch afterCommit` → `exchange user_events topic routing=user.created|user.updated` (persistent, correlation_id uuid)
  - Retry 5 backoff [5,15,30,60,120], `failed` sau 5 lần, `next_retry_at`
  - **Verify SELECT trước (db-rules):**
  ```bash
  # Khi RabbitMQ down, check tồn đọng:
  docker exec educonnect-dev-mysql-identity-1 mysql -uidentity_user -p$IDENTITY_DB_PASSWORD -e "SELECT status, count(*) FROM identity_db.outbox_events GROUP BY status; SELECT id,event_type,aggregate_id,status,attempts FROM identity_db.outbox_events WHERE status='pending' LIMIT 5;"
  ```

- [ ] **1c-2 Consumer School (PHP)**
  - `ConsumeUserEvents.php:27 EXCHANGE=user_events QUEUE=school_users_sync binding user.#`
  - Cần bổ sung DLX + quorum + idempotency (copy pattern `ConsumeAuthEvents.php:55,108`):
  ```php
  $channel->queue_declare(self::QUEUE, false, true, false, false, false, [
      'x-queue-type'=>['S','quorum'],
      'x-dead-letter-exchange'=>['S','educonnect.dlx'],
  ]);
  // Idempotency: Redis SETNX user:idempotency:{event_id} EX 3600
  // trước updateOrCreate
  ```
  - **Verify:**
  ```bash
  docker compose up -d rabbitmq mysql-school redis
  docker exec educonnect-dev-rabbitmq-1 rabbitmqctl list_bindings source destination routing_key | grep user_events
  php artisan consume:user-events --once --verbose
  docker exec educonnect-dev-mysql-school-1 mysql -uschool_user -p$SCHOOL_DB_PASSWORD -e "SELECT count(*) FROM school_db.users_read_model; SELECT * FROM school_db.users_read_model LIMIT 3;"
  docker compose logs school-worker --tail=20 | grep "corr="
  ```

- [ ] **1c-3 Consumer Finance (Go)**
  - `services/finance/main.go:57 startUserSyncConsumer queue=finance_users_sync exchange=user_events binding user.#` → upsert `users_read_model` (json roles)
  - Cần thêm DLX/quorum/idempotency như School, và fix `main.go:103 QueueDeclare` thiếu args

- [ ] **1c-4 E2E trace với correlation_id**
  ```bash
  # Tạo user mới (sẽ trigger outbox)
  php artisan tinker --execute="App\Domains\Identity\Models\User::factory()->create(['email'=>'e2e-'.time().'@test.local'])"
  # Check outbox published
  docker exec educonnect-dev-mysql-identity-1 mysql -uidentity_user -p$IDENTITY_DB_PASSWORD -e "SELECT id,event_type,status FROM identity_db.outbox_events ORDER BY id DESC LIMIT 2"
  # Check school & finance sync
  sleep 3; docker exec educonnect-dev-mysql-school-1 mysql -uschool_user -p$SCHOOL_DB_PASSWORD -e "SELECT * FROM school_db.users_read_model ORDER BY id DESC LIMIT 2"
  docker exec educonnect-dev-mysql-finance-1 mysql -ufinance_user -p$FINANCE_DB_PASSWORD -e "SELECT * FROM finance_db.users_read_model ORDER BY id DESC LIMIT 2"
  ```

### 1d. Giữ `FinanceApiClient.php` làm ACL

- [ ] ACL giữ `baseUrl = env('FINANCE_SERVICE_URL','http://finance:8080')`, 3 methods: `getInvoicesByStudent, createInvoice, getDashboardFinancials` (fallback 0 nếu Go chưa `/stats`)
- [ ] Dashboard đã dùng `DashBoardService → FinanceApiClient::getDashboardFinancials()` thay vì `Payment::sum()` (`MICROSERVICE-MIGRATION.md:8`)
- [ ] Không cho School import `App\Domains\Finance\Models` (đã xoá)
- [ ] Document future: khi Go có gRPC `InvoiceService`, giữ Http ACL tới khi transcoding xong, inter-service đi gRPC (`plan:2c`)

### 1e. ETL legacy `mysql` (profiles legacy) — chỉ doc, không chạy prod

- [ ] Draft script `scripts/etl-legacy-mysql-draft.sh` (đã tạo) + `docs/microservices/etl-legacy-mysql.md` (optional)
- [ ] Quy trình tuân `docs/db-rules.md`:
  1. `SELECT count(*) FROM laravel.*` trên legacy
  2. `mysqldump --single-transaction --quick` từng DB logical
  3. `mysql` restore vào `mysql-identity/school/finance` với user riêng
  4. Verify counts + checksum
  5. Không `DROP/DELETE` trên prod khi chưa `Confirm yes`
- [ ] **Verify (staging/local, KHÔNG prod):**
  ```bash
  docker compose --profile legacy up -d mysql
  docker compose up -d mysql-identity mysql-school mysql-finance
  ./scripts/etl-legacy-mysql-draft.sh --dry-run
  ./scripts/etl-legacy-mysql-draft.sh --verify-only
  ```

### 1f. Verify tổng hợp (theo `plan:Phase 0 + Phase 1 verify`)

```bash
# 0. Prep
git fetch origin && git status
docker compose config --quiet && echo "compose OK"          # phải 0 error, 10 services
docker compose up -d --build
docker compose ps --format "table {{.Name}} {{.Status}}"

# 1. Migrate per-service (local)
php artisan migrate:status --database=identity --path=database/migrations/identity
php artisan migrate:status --database=school   --path=database/migrations/school
php artisan migrate:status --database=finance  --path=database/migrations/finance  # sau move

# 2. Routes
php artisan route:list | grep -E "api/(admin|auth|grades|schedules|disciplines|health|jwks)" | wc -l
php artisan route:list --path=api/auth | grep -q login && echo "auth routes OK (0 trong school, gateway route tới identity:9000)"

# 3. Worker
docker compose logs school-worker --tail=10 | grep -q "school_users_sync.*user.#" && echo "worker OK"
docker exec educonnect-dev-rabbitmq-1 rabbitmqctl list_bindings | grep "user.#"

# 4. Health
curl -s http://localhost:8080/api/health | jq
curl -s http://localhost:8080/health/identity | jq
curl -s http://localhost:8080/api/finance/health | jq   # Go finance :8080/health

# 5. Test (cần DB testing hoặc sqlite)
# phpunit.xml đang 127.0.0.1:3307 -> cần compose legacy mysql hoặc đổi sang sqlite memory
php artisan test --filter=Auth --stop-on-failure
go test ./...  # services/finance
```

> Lưu ý phpunit hiện 41 failed do `SQLSTATE Connection refused testing@127.0.0.1:3307` — không phải lỗi code, do MySQL testing chưa up. Fix: `docker compose --profile legacy up -d mysql` + tạo `testing` DB hoặc đổi `phpunit.xml` sang `DB_CONNECTION=sqlite DB_DATABASE=:memory:`.

---

## 2. Files đã tạo/sửa (Phase 1 skeleton)

| File | Mô tả | Trạng thái |
|------|-------|------------|
| `database/migrations/finance/README.md` | Hướng dẫn move 6 files, cách migrate per-service, db-rules, Go AutoMigrate drift note | ✅ Mới tạo |
| `scripts/etl-legacy-mysql-draft.sh` | Draft ETL mysqldump|mysql 3 DB, dry-run + verify counts, không tự chạy prod | ✅ Mới tạo |
| `docs/microservices/phase-1-db-per-service-checklist.md` | File này — checklist chi tiết | ✅ Mới tạo |
| `database/migrations/finance/` | Thư mục mới, chờ `git mv` 6 files | ⏳ Chờ thực thi 1a-1 |
| `config/database.php` | Sửa finance host fallback | ⏳ Chờ 1a-3 |
| `app/Console/Commands/ConsumeUserEvents.php` | Thêm DLX/quorum/idempotency | ⏳ Đề xuất, chưa code |

---

## 3. Lệnh verify nhanh (coppy-paste)

```bash
# Check migrations chưa move
ls -1 database/migrations/*.php | grep -E "0223|fee|invoice|payment" && echo "⚠️ còn ở root" || echo "✅ root sạch"

# Check connections
grep -n "connection.*identity\|connection.*school\|connection.*finance" app/Domains/*/**/Models/*.php

# Check docker compose
docker compose config 2>&1 | grep -E "mysql-identity|mysql-school|mysql-finance|school-worker" | head -20

# Check routes (expect ~127 hiện tại, plan 104 do chưa filter horizon/scribe)
php artisan route:list 2>&1 | tail -5

# Check finance ACL còn giữ
ls -1 app/Domains/Finance/Services/FinanceApiClient.php && echo "ACL OK"

# Dry-run ETL
bash scripts/etl-legacy-mysql-draft.sh --help
bash scripts/etl-legacy-mysql-draft.sh --dry-run
```

---

## 4. Cam kết không đụng prod

- Không ghi `/home/robert/production` (đã verify `ls /home/robert/production` là deploy spec, không phải git repo — giữ nguyên).
- Không chạy `DROP/DELETE/TRUNCATE/ALTER` trên prod; mọi ETL chỉ `SELECT count(*)` + `mysqldump` trên `profiles:legacy` local.
- Mọi migrate test trên `mysql-*-1` local trước, prod chỉ sau `Confirm yes`.

---

## 5. Next Phase 2 (không làm đợt này)

- `proto/school.proto` đã có skeleton `proto/school.proto` (untracked) → gen `internal/pkg/proto/school/*.pb.go`
- Go gRPC servers `services/identity-grpc` + `school-grpc` (50051/50053), finance implement `InvoiceService` thay Unimplemented
- CQRS pilot Finance trước, ES cho Identity/Finance (event_store, outbox, projector, replay)
