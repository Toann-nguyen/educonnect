# Phase 3 — CQRS read/write + Event Sourcing full — CHECKLIST (draft, design-only)

> Branch: `chore/infra-devops` only · Không migrate prod · Không đụng `/home/robert/production` · Finance pilot, Identity + Finance ES, School chỉ CQRS logical trước
> Plan tổng: `docs/microservices/plan-chore-infra-devops-grpc-cqrs.md` (Phase 3:51 lines 104-120)
> Evidence hiện trạng: `OutboxEvent.php:identity`, `PublishUserEventJob.php`, `Observers/UserObserver.php`, `UsersReadModel.php:school`, `services/finance/internal/model/models.go`, `IdempotencyMiddleware.php`, `gateway.conf.template:51`, `nginx.conf:25`, `ConsumeAuthEvents.php:55,108`, `FinanceApiClient.php:67`

---

## 1) Tóm tắt CQRS vs Event Sourcing (cho Phase 3)

| Nhóm | CQRS | ES |
|------|------|----|
| Mục đích | Tách **read** (Query) và **write** (Command) để scale/optimize riêng | Lưu lịch sử domain như **chuỗi event append-only** thay vì update row trực tiếp |
| Storage | Command ghi write-model (`users`, `invoices`…); Query đọc read-model / view + Redis | `event_store` (append-only) là source of truth; projector rebuild `users_read_model`, `finance_users_sync`, `audit_logs`, `student_conduct_scores` |
| Sync | Sync read-model qua RabbitMQ topic (`user_events:user.#`, `finance_events:finance.#`) + projector | Outbox `outbox_events` → Job publish → exchange → consumer projector → upsert read-model |
| Idempotency | `IdempotencyMiddleware.php:46` `Redis SET NX EX 60` lock + `idempotency_response` cache 86400 | Consumer `ConsumeAuthEvents.php:108` `Redis SET NX EX 86400` + `outbox.idempotency_key` (migration `2026_09_05_000002`) → ack duplicate |
| Điểm mạnh Phase 3 | Finance pilot: `CreateInvoiceCommand` / `GetInvoicesByStudentQuery` / `GetStatsQuery` tách rõ handler, `School: CreateStudentCommand, UpdateGradeCommand, GetMyGradesQuery, GetClassScheduleQuery` chỉ logical CQRS trước | Replay `event_store` rebuild, snapshot mỗi 100 event, DLX `educonnect.dlx` quorum queue, correlation_id trace xuyên gateway→gRPC metadata |
| Rủi ro nếu nhầm | Dùng Query để ghi, hoặc Command trả data nặng | Update `users/invoices` trực tiếp mà không append `event_store` → mất tính audit/replay |

**Pilot Finance trước**: Finance đã có gRPC skeleton (`services/finance/internal/grpc/server.go` Unimplemented) và HTTP `StatsHandler.php` → cắt CQRS xong mới thay `FinanceApiClient::getDashboardFinancials()` fallback 0 bằng `GetStatsQuery`.

---

## 2) Hiện trạng verify (đã đọc)

- `identity outbox_events` đã có: `2026_09_03_000001` + `2026_09_05_000002 idempotency_key` index, model `OutboxEvent.php:9 identity connection`, `PublishUserEventJob.php:55 exchange user_events topic` retry 5 backoff `[5,15,30,60,120]`, `UserObserver.php:14,61 enqueue user.created/updated/deleted` + bump `token_version` → `TokenRevocationService` → `AuthEventPublisher.php` publish `auth.events` → `ConsumeAuthEvents.php:55 quorum + x-dead-letter educonnect.dlx`, `ConsumeUserEvents.php:58 school_users_sync user.#` → `UsersReadModel.php:school`.
- Gateway: `nginx.conf:25 map $http_x_correlation_id $correlation_id else $request_id`, `gateway.conf.template:51 add_header X-Correlation-ID $correlation_id`, `json_gateway` log, pass `HTTP_X_CORRELATION_ID` tới fastcgi/gRPC proxy; `IdempotencyMiddleware.php:48 SET NX EX60` + 409 đang xử lý.
- `services/finance/internal/model/models.go` canonical `Invoice/Payment/FeeType/UserReadModel:users_read_model`, consume `finance_users_sync` trong `main.go:handleUserEvent`.
- `proto/school.proto` đã tạo (StudentService/ClassService/GradeService/ScheduleService) + `finance.proto InvoiceService/PaymentService`.
- Chưa có: `event_store` append-only, CommandBus/QueryBus, Projector chính quy, snapshot, `GetStatsQuery` thay fallback, DLX đầy đủ cho finance, idempotencyKey header qua gRPC metadata.

---

## 3) Checklist chi tiết Phase 3 (design-only, chưa migrate)

### 3a. Event Store schema (append-only, không update users/invoices trực tiếp)

**3a.1 DB tách per service `config/database.php:identity|school|finance` — tạo `event_store` trên `identity` + `finance` (School chưa ES)**

- [ ] Migration draft (xem §5 SQL): `event_store(id BIG PK, aggregate_type VARCHAR50, aggregate_id BIGINT, version INT, event_type VARCHAR100, payload JSON, metadata JSON{correlation_id, idempotency_key, actor_id, ip}, occurred_at DATETIME, created_at)`, index `(aggregate_type, aggregate_id, version) UNIQUE`, index `(event_type, occurred_at)`, index `(correlation_id)` — **không thêm FK để giữ append-only**.
- [ ] Migration `event_snapshot` (snapshot mỗi 100 event): `aggregate_type, aggregate_id, version, snapshot_payload JSON, created_at`, PK `(aggregate_type, aggregate_id, version)`.
- [ ] Migration `finance.event_store` tương tự, thêm `finance_snapshots`.
- [ ] `OutboxEvent` giữ nguyên làm transport; projector consume `user.created/user.updated` + `finance.invoice.created/paid` từ `event_store` qua `afterCommit` dispatch Job → RabbitMQ (`user_events: user.#`, `finance_events: finance.#`). `event_store.version` tăng monotonic per aggregate (optimistic lock `WHERE version = max+1`, nếu conflict retry).
- [ ] Seed không chạm prod: `php artisan migrate --database=identity --path=database/migrations/identity --pretend` + `migrate --database=finance` chỉ dry-run; prod chỉ doc `docs/microservices/phase-3-migration-plan.md` hướng dẫn.

**3a.2 Quy tắc**
- [ ] Không `UPDATE/DELETE` trên `event_store`; chỉ `INSERT`. Đọc qua `SELECT * FROM event_store WHERE aggregate_id=? ORDER BY version`.
- [ ] `correlation_id` sinh tại Gateway (`$correlation_id`) → Header `X-Correlation-ID` → Laravel `request()->header('X-Correlation-ID')` lưu vào `payload.correlation_id` + `AMQPMessage correlation_id` + `application_headers x-correlation-id` cho Go `handleUserEvent` đọc; Go gRPC metadata `x-correlation-id`.
- [ ] `idempotency_key` sinh client (`Idempotency-Key` header) hoặc `Str::uuid()` trong `AuthEventPublisher.php:35` → lưu `outbox_events.idempotency_key` + `event_store.metadata.idempotency_key` → consumer `Redis SET NX auth:idempotency:{key} EX 86400` như `ConsumeAuthEvents.php:108`.

### 3b. CQRS logical — CommandBus / QueryBus

#### School (Laravel, chỉ logical, chưa ES)

- [ ] Tạo `app/Domains/School/Application/Commands/{CreateStudentCommand, UpdateGradeCommand}` + Handlers:

```php
// Commands là DTO readonly, không business logic
final readonly class CreateStudentCommand { public function __construct(
  public int $userId, public int $classId, public string $studentCode, public string $correlationId, public ?string $idempotencyKey
) {} }
final readonly class UpdateGradeCommand { public function __construct(
  public int $gradeId, public float $score, public int $actorId, public string $correlationId, public ?string $idempotencyKey
) {} }
```

- [ ] Handlers: validate → authorize (Policy `StudentPolicy`, `GradePolicy`) → Eloquent write-model `school` → dispatch event in-memory (chưa lưu event_store ở Phase 3b, chỉ publish `user_events` nếu liên quan) → return resource.
- [ ] Queries: `GetMyGradesQuery(studentId, semester?, subjectId?, pagination)` đọc `school.grades + Redis PermissionCacheService` (đã có `config/database.php:redis`), `GetClassScheduleQuery(classId|teacherId, pagination)` đọc `school.schedules` + `users_read_model` join.
- [ ] Buses:

```php
// app/Domains/Shared/Bus/CommandBus.php
interface CommandBus { public function dispatch(object $command): mixed; }
// InMemoryCommandBus: map FQCN → handler closure, middleware: idempotency → correlationId → logging → transaction
// QueryBus tương tự, read-only, cacheable
```

- [ ] Middleware cho CommandBus: `IdempotencyMiddleware` tái sử dụng logic `Redis SET NX idempotency_lock:{key} EX 60` → 409 nếu duplicate; success cache `idempotency_response:{key} EX 86400`.
- [ ] Controller refactor: `School/Http/Controllers/StudentController::store(CreateStudentRequest)` → `$bus->dispatch(new CreateStudentCommand(...))` thay vì gọi Service trực tiếp; `GradeController::__invoke(GetMyGradesQuery)`.

#### Finance (Go, pilot ES full)

- [ ] `services/finance/internal/command/{CreateInvoiceCommand, Handler}`:

```go
type CreateInvoiceCommand struct {
  StudentID int64; UserID int64; Items []Item; Description string; DueDate time.Time; CorrelationID string; IdempotencyKey string
}
type CommandHandler interface { Handle(ctx context.Context, cmd CreateInvoiceCommand) (*model.Invoice, error) }
// Handler: validate FeeType is_active → tính total → GORM tx: insert invoice+items → append event_store finance.invoice.created v1 → commit → publish finance_events topic finance.invoice.created (AMQP persistent + correlation_id + message_id=idempotency_key)
```

- [ ] Queries: `GetInvoicesByStudentQuery(studentId, pagination)` đọc read-model (chính `invoices` hiện là write-model, Phase 3c sẽ tách view `invoices_read` nếu cần); `GetStatsQuery()` thay `FinanceApiClient::getDashboardFinancials()` fallback 0 — handler đọc `payments` + `invoices` aggregate như `handler/stats.go:28-36` nhưng qua QueryBus cache 60s.

```go
type GetInvoicesByStudentQuery struct { StudentID int64; Page, Limit int }
type GetStatsQuery struct { CorrelationID string } // revenue_today, revenue_this_month, overdue_invoices
```

- [ ] `services/finance/internal/bus/{CommandBus, QueryBus}` in-memory map, middleware `idempotency` (redis `SETNX invoices:idempotency:{key} EX 86400`), `correlation` (grpc metadata `x-correlation-id`), `tx`.
- [ ] gRPC `InvoiceServiceServer` (hiện Unimplemented) implement gọi CommandBus/QueryBus thay vì TODO.

#### Dashboard thay fallback 0

- [ ] Laravel `FinanceApiClient::getDashboardFinancials()` hiện fallback `['revenue_today'=>0...]` (`FinanceApiClient.php:80`) → đổi thành gọi nội bộ Go `GET /api/finance/stats` **qua QueryBus** nếu có `GetStatsQuery` cache; giữ fallback chỉ khi Go down (log warn) chứ không mặc định 0.

### 3c. Event Sourcing — Identity + Finance (School hoãn ES)

- [ ] Identity: `RegisterUserCommand` → `UserRegistered v1 {name,email,roles,is_active,correlation_id}` → `identity.event_store` version 1 → `outbox_events user.created` → RabbitMQ `user.created` → Projector (Laravel `UserProjector`) → upsert `users_read_model` (school) + `finance_users_sync` (Go) + `audit_logs` (identity). `UpdateUserCommand` → `UserUpdated/ProfileUpdated/StatusChanged/TokenVersionBumped` v2…
- [ ] Finance: `InvoicePaid v1 {invoiceId, amount, method, paidBy}` → projector update `student_conduct_scores`? (hiện `school.student_conduct_scores` discipline, Phase 3c mapping `InvoicePaid` không đổi conduct, chỉ demo `audit`). Chủ yếu sync `finance_users_sync` đã có.
- [ ] Không viết trực tiếp `users` hay `invoices` ngoài Handler; read path chỉ qua projector view.

### 3d. Projector & Replay

- [ ] `app/Domain/Identity/Projectors/UserProjector.php` (Laravel) + `services/finance/internal/projector/UserProjector.go` + `InvoiceProjector.go`:

```
consume user_events (quorum, DLQ)  → UserProjector.handle(event_type):
  user.created → UsersReadModel::updateOrCreate (đã có ConsumeUserEvents.php:91)
  user.updated → UsersReadModel update + PermissionCacheService::clearUser
  user.deleted → UsersReadModel soft-delete hoặc is_active=false
consume finance_events (finance.#) → InvoiceProjector:
  finance.invoice.created → (đã trong tx, không duplicate)
  finance.invoice.paid → update invoice.status=paid
```

- [ ] Idempotency trong projector: `Redis SETNX projector:{exchange}:{idempotency_key} EX 86400` như `ConsumeAuthEvents.php:108` trước khi upsert; nếu duplicate → ack.
- [ ] Replay: `php artisan projector:replay --aggregate=user --from=0` đọc `event_store ORDER BY version` → rebuild `users_read_model` (truncate view rồi re-apply); Go `go run ./cmd/replay --aggregate=invoice`. Phải idempotent (upsert).
- [ ] Snapshot mỗi 100 event: `event_snapshot` lưu `aggregate payload` tại version %100==0; replay từ snapshot gần nhất + 100 event tiếp.

### 3e. DLX, Retry, Quorum

- [ ] Tất cả queue quorum + `x-dead-letter-exchange: educonnect.dlx` như `ConsumeAuthEvents.php:55` (đã có `auth.events` + `user_events school_users_sync`), bổ sung `finance_users_sync` + `school_auth_events` + `finance_events` consumer.
- [ ] Khai báo DLQ `*.dlq` bind `educonnect.dlx` như `ConsumeAuthEvents.php:61`.
- [ ] Publish retry 5 lần `PublishUserEventJob.php:23 backoff [5,15,30,60,120]`; consumer `Nack(requeue=false) → DLQ` không loop.
- [ ] `rabbitmqctl list_bindings source destination routing_key` phải `user.# finance.# auth.#` không phải `user.*` (pitfall `09-infrastructure.md:171`), verify sau deploy.

### 3f. Idempotency (header + Redis SETNX như ConsumeAuthEvents:55,108)

- [ ] `IdempotencyMiddleware.php:48 SET NX EX60 lock`, `Redis::exists responseKey` cache `isSuccessful` 86400 — áp cho `POST /api/students, /api/grades, /api/finance/invoices` và gRPC `CreateInvoice` (lấy `Idempotency-Key` từ header `X-Idempotency-Key` / gRPC metadata `idempotency-key`).
- [ ] Client: `gateway.conf.template` đã expose `Idempotency-Key` trong CORS `Allow-Headers`; Gateway không strip; forward `HTTP_IDEMPOTENCY_KEY` → Laravel + gRPC metadata.
- [ ] Go consumer: `Redis SET NX invoices:idempotency:{key}` trước khi `Handle CreateInvoiceCommand` → nếu duplicate trả về response cache (như middleware PHP).

### 3g. Snapshot strategy 100

- [ ] Sau mỗi `version % 100 == 0` → `INSERT event_snapshot` với `snapshot_payload = JSON aggregate state` (user + roles, invoice + items).
- [ ] Replay optimization: `SELECT * FROM event_snapshot WHERE aggregate_id=? ORDER BY version DESC LIMIT 1` → `SELECT * FROM event_store WHERE aggregate_id=? AND version > snapshot.version`.

### 3h. Verification (không migrate prod, chỉ design + test local)

- [ ] Unit: `app/Domains/School/Application/Commands/*HandlerTest` + `services/finance/internal/command/*_test.go`
- [ ] Integration: publish `user.created` → `finance_users_sync` row, `school users_read_model` row (dùng `consume:user-events --once` + `go test -run TestHandleUserEvent`)
- [ ] Replay: seed `event_store` 250 rows → `php artisan projector:replay` → assert row count match
- [ ] gRPC: `grpcurl -plaintext localhost:50051 list` thấy `school.StudentService, finance.InvoiceService` sau khi implement, `GetUser` đọc read-model
- [ ] Load: spam `Idempotency-Key` duplicate → expect 1 write + 1 cached 200, không duplicate row
- [ ] DLX: nuke message poison → vào `*.dlq` quorum, không mất

---

## 4) Draft schema SQL (chưa chạy, chỉ design)

```sql
-- ============================================================
-- event_store — append-only, Identity + Finance (per DB)
-- Chạy dry: php artisan migrate --pretend (identity, finance)
-- Không chạy trên production/ trước approve
-- ============================================================

-- Identity DB (identity.event_store)
CREATE TABLE `identity`.`event_store` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `aggregate_type` VARCHAR(50) NOT NULL COMMENT 'user|role|permission',
  `aggregate_id` BIGINT UNSIGNED NOT NULL,
  `version` INT UNSIGNED NOT NULL,
  `event_type` VARCHAR(100) NOT NULL COMMENT 'UserRegistered|UserUpdated|UserDeactivated|TokenVersionBumped|...',
  `payload` JSON NOT NULL,
  `metadata` JSON NULL COMMENT '{correlation_id, idempotency_key, actor_id, ip}',
  `occurred_at` DATETIME(3) NOT NULL,
  `created_at` DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_agg_version` (`aggregate_type`,`aggregate_id`,`version`),
  KEY `idx_event_occurred` (`event_type`,`occurred_at`),
  KEY `idx_correlation` ((CAST(`metadata`->>'$.correlation_id' AS CHAR(36)))),
  KEY `idx_idempotency` ((CAST(`metadata`->>'$.idempotency_key' AS CHAR(100))))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `identity`.`event_snapshot` (
  `aggregate_type` VARCHAR(50) NOT NULL,
  `aggregate_id` BIGINT UNSIGNED NOT NULL,
  `version` INT UNSIGNED NOT NULL,
  `snapshot_payload` JSON NOT NULL,
  `created_at` DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
  PRIMARY KEY (`aggregate_type`,`aggregate_id`,`version`),
  KEY `idx_agg_latest` (`aggregate_type`,`aggregate_id`,`version` DESC)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Finance DB (finance.event_store) — aggregate invoice|payment
CREATE TABLE `finance`.`event_store` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `aggregate_type` VARCHAR(50) NOT NULL COMMENT 'invoice|payment',
  `aggregate_id` BIGINT UNSIGNED NOT NULL,
  `version` INT UNSIGNED NOT NULL,
  `event_type` VARCHAR(100) NOT NULL COMMENT 'InvoiceCreated|InvoicePaid|InvoiceCancelled|PaymentCreated',
  `payload` JSON NOT NULL,
  `metadata` JSON NULL,
  `occurred_at` DATETIME(3) NOT NULL,
  `created_at` DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_agg_version` (`aggregate_type`,`aggregate_id`,`version`),
  KEY `idx_event_occurred` (`event_type`,`occurred_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `finance`.`event_snapshot` (
  `aggregate_type` VARCHAR(50) NOT NULL,
  `aggregate_id` BIGINT UNSIGNED NOT NULL,
  `version` INT UNSIGNED NOT NULL,
  `snapshot_payload` JSON NOT NULL,
  `created_at` DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
  PRIMARY KEY (`aggregate_type`,`aggregate_id`,`version`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Laravel migration stub (identity: 2026_09_10_000001_create_event_store_table.php)
-- Schema::connection('identity')->create('event_store', fn(Blueprint $t)=>{
--   $t->id(); $t->string('aggregate_type',50); $t->unsignedBigInteger('aggregate_id');
--   $t->unsignedInteger('version'); $t->string('event_type',100); $t->json('payload'); $t->json('metadata')->nullable();
--   $t->timestamp('occurred_at',3); $t->timestamp('created_at',3)->useCurrent();
--   $t->unique(['aggregate_type','aggregate_id','version'],'uq_agg_version');
--   $t->index(['event_type','occurred_at']); // correlation via generated column if needed
-- });
```

Laravel migration file mẫu đã để trong §6 Files tạo.

---

## 5) Files tạo (dự kiến, trên chore/infra-devops — chưa commit prod)

```
docs/microservices/phase-3-cqrs-es-checklist.md  ← file này
docs/microservices/phase-3-cqrs-es-design.md     (optional chi tiết sequence)
database/migrations/identity/2026_09_10_000001_create_event_store_table.php
database/migrations/identity/2026_09_10_000002_create_event_snapshot_table.php
database/migrations/finance/2026_09_10_000001_create_event_store_table.php   (hoặc services/finance/migrations/*.sql cho gorm AutoMigrate)
database/migrations/finance/2026_09_10_000002_create_event_snapshot_table.php
app/Domains/Identity/Application/Commands/RegisterUserCommand.php + Handler.php
app/Domains/Identity/Projectors/UserProjector.php
app/Domains/School/Application/Commands/CreateStudentCommand.php
app/Domains/School/Application/Commands/UpdateGradeCommand.php
app/Domains/School/Application/Commands/Handlers/CreateStudentHandler.php
app/Domains/School/Application/Commands/Handlers/UpdateGradeHandler.php
app/Domains/School/Application/Queries/GetMyGradesQuery.php
app/Domains/School/Application/Queries/GetClassScheduleQuery.php
app/Domains/School/Application/Queries/Handlers/GetMyGradesHandler.php
app/Domains/School/Application/Queries/Handlers/GetClassScheduleHandler.php
app/Domains/Shared/Bus/{CommandBus,QueryBus,InMemoryCommandBus,InMemoryQueryBus,Middleware/IdempotencyMiddlewareBus,LoggingMiddleware}.php
app/Console/Commands/ProjectorReplay.php  (php artisan projector:replay)
app/Console/Commands/ConsumeUserEvents.php  (đã có — bổ sung idempotency SETNX)
services/finance/internal/command/{create_invoice.go, handler.go}
services/finance/internal/query/{get_invoices_by_student.go, get_stats.go, handler.go}
services/finance/internal/bus/{command_bus.go, query_bus.go}
services/finance/internal/projector/{invoice_projector.go, user_projector.go}
services/finance/internal/eventstore/{store.go, snapshot.go}
services/finance/cmd/replay/main.go
proto/school.proto (đã tạo — bổ sung GetMyGradesRequest nếu tách Query gRPC)
```

**Không tạo** trên `production/`; mọi migration chỉ `--pretend` local.

---

## 6) Lệnh verify (local / staging — không prod)

```bash
# Branch
git -C /home/robert/educonnect branch --show-current  # expect chore/infra-devops

# Laravel — dry migrate, không chạm prod
php artisan migrate --database=identity --path=database/migrations/identity --pretend
php artisan migrate --database=school --path=database/migrations/school --pretend
php artisan migrate --database=finance --path=database/migrations/finance --pretend
php artisan route:list | grep -E "students|grades|schedules|finance"
composer dump-autoload

# Tests
php artisan test --filter=Auth           # smoke Identity
php artisan test --filter=School         # CreateStudentCommand, GetMyGradesQuery
php artisan test tests/Feature/ProjectorReplayTest.php

# Go Finance — command/query + projector
go test ./services/finance/... -run TestCreateInvoiceCommand -count=1
go test ./services/finance/internal/eventstore -run TestAppendAndReplay -count=1
go test ./...                            # all

# Replay (sau seed event_store 250 rows)
php artisan projector:replay --aggregate=user --from=0 --dry
go run ./services/finance/cmd/replay --aggregate=invoice --from-snapshot

# RabbitMQ bindings (pitfall #)
rabbitmqctl list_bindings source_name destination_name routing_key | grep -E "user_events|finance_events|auth.events"
# expect user.# finance.# auth.# (không phải user.*)

# Gateway + correlation_id
curl -i http://localhost:8080/api/health | grep -i X-Correlation-ID
curl -i -H "Idempotency-Key: 123e4567-e89b-12d3-a456-426614174000" -H "X-Correlation-ID: test-corr-1" http://localhost:8080/api/finance/invoices?student_id=1 | grep -i correlation

# Idempotency duplicate test (expect 2nd trả cached, không tạo duplicate invoice)
for i in 1 2; do curl -s -X POST http://localhost:8080/api/finance/invoices -H "Idempotency-Key: dup-1" -H "Content-Type: application/json" -d '{"student_id":1,"items":[]}' | jq .; done

# DLX check
rabbitmqctl list_queues name consumers messages | grep -E "school_users_sync|finance_users_sync|school_auth_events"
rabbitmqctl list_queues name | grep dlq

# gRPC (sau implement)
grpcurl -plaintext localhost:50051 list | grep -E "InvoiceService|StudentService|GradeService"
grpcurl -plaintext -d '{"student_id":"1"}' localhost:50051 finance.InvoiceService/GetInvoicesByStudent
```

---

## 7) Rollout order (commit nhỏ, verify từng bước)

```
chore(db): draft event_store + snapshot migrations (pretend only)        ← Phase3-1
feat(cqrs): CommandBus/QueryBus shared + School CreateStudent/UpdateGrade + GetMyGrades/GetClassSchedule (logical) ← Phase3-2
feat(cqrs): Finance pilot Command CreateInvoice + Query GetInvoicesByStudent/GetStats (thay fallback 0)           ← Phase3-3
feat(es): Identity event_store append RegisterUser/UserUpdated + UserProjector → users_read_model/finance_users_sync   ← Phase3-4
feat(es): Finance event_store InvoiceCreated/Paid + InvoiceProjector + snapshot 100                               ← Phase3-5
feat(es): projector replay cmd + DLX verify + redis SETNX idempotency (ConsumeAuthEvents:108 pattern)              ← Phase3-6
docs(gateway): correlation_id via gRPC metadata + Idempotency-Key forward (no code prod/)                           ← Phase3-7
```

Mỗi commit `git diff --stat` + `docker compose config` + `php artisan test` + `go test ./...` trước push. Không `rm -rf`, không `--force` prod.

---

## 8) Constraints đã tuân thủ

- `chore/infra-devops` only, không merge `main` khi chưa rebase xong (behind 38).
- Redis idempotency `SETNX EX` như `ConsumeAuthEvents.php:55 quorum` và `:108 SET NX`.
- Gateway `X-Correlation-ID` từ `nginx.conf:25` + `gateway.conf.template:51` xuyên `fastcgi_param HTTP_X_CORRELATION_ID` và gRPC metadata.
- Không migration prod, chỉ design + `--pretend`.
- Không edit `production/` (`/home/robert/production`).

---

*Draft generated 2026-09-24 on chore/infra-devops — ready for `opencode run "implement Phase 3a event_store migration"` từng atomic task.*
