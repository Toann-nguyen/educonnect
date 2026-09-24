# Plan chi tiết — chore/infra-devops: Go gRPC full + CQRS/ES + Nginx | mono-repo

> Branch: `chore/infra-devops` (HEAD 8d3b9a1 = origin/chore/infra-devops). Rebase từ `main` fd8a303 (behind 38). `production/` (/home/robert/production) chỉ spec, không code đợt này.
> Laya: plan 0.38 (conf 0.06), effort 1.27 medium→complex, risk 0.68 cao — phải làm từng bước, verify kỹ, không đụng prod khi chưa approve.
> Chốt: giữ PHP Laravel cho Identity/School, đổi hẳn Go gRPC (bỏ PHP gRPC), CQRS tách read/write + Event Sourcing full, giữ nginx hiện tại, mono-repo chung ~/educonnect.

---

## 0. Hiện trạng (evidence)

- Monolith Laravel 10 PHP 8.1 (`composer.json:7`, `TECH-STACK.md:1`) DDD `app/Domains/Identity|School|Finance` (`docs/architecture/03-domain-architecture.md:4`). School 14 models/14 controllers, Identity 11 models RBAC Spatie, Finance đã xóa stub PHP chỉ còn `FinanceApiClient.php` (`docs/MICROSERVICE-MIGRATION.md:8`).
- Đã split: `docker-compose.yml:gateway` nginx 1.25 `gateway.conf.template:21` route `/api/auth→identity:9000` fastcgi, `/api/finance→finance:8080` proxy, `/api/*→php:9000`, `/socket.io→realtime:3000`, `log_format json_gateway` + `correlation_id` + CORS + `gw_auth 30r/m burst10`, `gw_api 120r/m burst30`.
- DB per service `config/database.php:identity|school|finance`, 3 MySQL `mysql-identity|school|finance` (`MICROSERVICE-MIGRATION.md:14`).
- RabbitMQ topic `user_events`/`finance_events`, `notification_queue` quorum + DLX `educonnect.dlx` (`09-infrastructure.md:72`), `services/notify|realtime` consume, `services/finance/main.go:1` + `internal/grpc/server.go:1` skeleton Unimplemented.
- Proto `proto/{auth,common,finance,notify}.proto` + `internal/pkg/proto/{auth,common,finance,notify}/*.pb.go` + `Makefile:generate`.

---

## Kiến trúc đích

```
Client → Nginx Gateway (:8080 dev, :80/:443 prod) → HTTP + gRPC
  ├─ identity-grpc (Go :50051) ─┬─→ identity_db (MySQL)  ─┐
  │  AuthService/UserService     │   + Redis              │  RabbitMQ
  ├─ school-grpc (Go :50053) ───┼─→ school_db   ─────────┤  user_events (topic user.#)
  ├─ finance (Go :8080 HTTP + :50051 gRPC) → finance_db  ─┤  finance_events (topic finance.#)
  ├─ notify (Go :50052 gRPC + consumer)                  ─┤  notification_queue (quorum, DLX)
  └─ realtime (Node :3000 Socket.IO)                     ┘
  php (Laravel) giữ HTTP legacy cho School/Identity tới khi cutover, không serve gRPC
```

- Cross-domain chỉ `user_id`/`student_id` (`03-domain-architecture.md:180`), sync via `user.#` → `finance_users_sync`, `UsersReadModel.php`.
- Gateway verify JWT RS256 qua JWKS `/.well-known/jwks.json` (`routes/web.php:16`, `JwksController`), Go services dùng `MicahParks/keyfunc` cache (`services/finance/go.mod`).

---

## Phase 0 — Chuẩn nhánh (0.5d) — BLOCK

```bash
git fetch origin && git rebase origin/main   # trên chore/infra-devops
composer dump-autoload && php artisan route:list  # expect 104 routes, 0 api/auth trong school
docker compose config  # validate
php artisan test --filter=Auth  # smoke
go test ./...  # services/finance, internal/pkg/proto
```

---

## Phase 1 — Tách business còn lại (Strangler Fig, 2-3d)

**1a. DB per service hoàn thiện**
- Move `2025_09_30_*fee_types|invoices|payments` từ `database/migrations/` root vào `database/migrations/finance/`, model `$connection='finance'|'identity'|'school'` như `03-domain-architecture.md:59`.
- `php artisan migrate --database=identity --path=database/migrations/identity` + `school`, Go finance `gorm AutoMigrate` trong `services/finance/main.go`.
- Bật `school-worker consume:user-events` → upsert `UsersReadModel`. Legacy `mysql` giữ `profiles: ["legacy"]` để `mysqldump | mysql` ETL (`MICROSERVICE-MIGRATION.md:42`).

**1b. Boundary**
- Identity: User, Profile, Role, Permission, RefreshToken, UserSession, EmailVerification, BackupCode, AuditLog (11 models `03-domain-architecture:59`)
- School: Student, SchoolClass, Subject, Schedule, Grade, Attendance, Discipline*, Library, Event, Guardian (18 models `03-domain-architecture:99`)
- Finance: Invoice, Payment, FeeType, InvoiceItem (Go canonical `MICROSERVICE-MIGRATION.md:50`) — xóa `app/Domains/Finance/*` chỉ giữ `FinanceApiClient.php:1` làm ACL.

Verify: `docker compose up -d --build` 10 healthy, `curl /api/health` + `X-Correlation-ID`, `curl /health/identity /health/finance`.

---

## Phase 2 — Go gRPC full (3-4d) — ĐỔI HẲN GO

**2a. Proto-first**
- Giữ `auth.proto:AuthService/UserService` (Login, RefreshToken, ValidateToken, Logout, GetUser, GetUsersByRole, CreateUser…), `finance.proto:InvoiceService`, `notify.proto:NotifyService`, `common.proto:Pagination, Money, UserRef`.
- **Thêm `proto/school.proto`**:
  ```proto
  service StudentService { rpc GetStudent(GetStudentRequest) returns (GetStudentResponse); rpc ListStudents(ListStudentsRequest) returns (ListStudentsResponse); rpc CreateStudent(CreateStudentRequest) returns (CreateStudentResponse); }
  service ClassService { ... }
  service GradeService { ... }
  service ScheduleService { ... }
  ```
  Dùng `common.PaginationRequest/Response`, `google.protobuf.Timestamp`.
- Gen: `make -C internal/pkg/proto generate` (`protoc --go_out --go-grpc_out --go-vtproto_out -Iproto`) → `internal/pkg/proto/{auth,common,finance,notify,school}/*.pb.go` + copy vào `services/*/internal/pkg/proto` via `replace educonnect/internal/pkg/proto => ../../internal/pkg/proto` (`services/finance/go.mod:5`). Không gen PHP stubs.

**2b. Go gRPC servers (mỗi service 1 container, không PHP serve gRPC)**

| Service | Path | Port | DB |
|---|---|---|---|
| identity-grpc | `services/identity-grpc` (mới, copy template finance) | 50051 | identity_db GORM trực tiếp |
| school-grpc | `services/school-grpc` (mới) | 50053 | school_db GORM |
| finance | `services/finance/internal/grpc/server.go:17` implement `InvoiceService` | 50051 (riêng container) / 8080 HTTP | finance_db |
| notify | `services/notify/internal/grpc/server.go` implement `NotifyService` | 50052 | — |

Tất cả đăng ký `google.golang.org/grpc@v1.70` + `vtprotobuf` + `grpc/health`, zap logger như `finance/main.go`.

**2c. Clients & cutover**
- `services/finance/internal/grpc/client.go`, `services/notify/internal/grpc/client.go` thêm `services/identity-grpc/internal/client` cho School gọi `ValidateToken`.
- Laravel không dùng `grpc/php`, giữ `FinanceApiClient.php:10` Http tới khi gRPC transcoding xong, inter-service đi gRPC (gateway vẫn HTTP).

**2d. Verify**
```bash
grpcurl -plaintext localhost:50051 list  # auth.AuthService, finance.InvoiceService, notify.NotifyService, school.StudentService
grpcurl -d '{"email":"test@edu.vn","password":"secret"}' localhost:50051 auth.AuthService/Login
docker logs identity-grpc finance | grep -v Unimplemented
rabbitmqctl list_bindings source destination routing_key  # phải là user.# finance.# không phải user.* (pitfall 09-infrastructure.md:171)
```

---

## Phase 3 — CQRS + Event Sourcing full (4-5d)

**3a. CQRS logical (pilot Finance trước)**
- Command: `services/finance/internal/command/CreateInvoiceCommand → Handler → aggregate Invoice → repo → event finance.invoice.created → RabbitMQ`, Laravel `app/Domains/School/Application/Commands/CreateStudentCommand, UpdateGradeCommand` + `CommandBus`.
- Query: `GetInvoicesByStudentQuery` đọc read-model, `GetMyGradesQuery`, `GetClassScheduleQuery` đọc Redis (`PermissionCacheService.php`, `REDIS_HOST` `config/database.php:redis`). Dashboard `FinanceApiClient:getDashboardFinancials` fallback 0 thay bằng `GetStatsQuery`.

**3b. Event Sourcing (Identity + Finance)**
- Event Store table `event_store` (append-only): `aggregate_id, version, event_type, payload json, occurred_at, correlation_id` — không update `users` trực tiếp.
- `RegisterUserCommand` → `UserRegistered v1` → store + `outbox_events` (`OutboxEvent.php`) → RabbitMQ `user.created` (`user_events` topic `user.#`).
- Projector: `UserProjector` consume `user.created/user.updated` → upsert `users_read_model`, `finance_users_sync`, `audit_logs`. Finance `InvoicePaid` → update `student_conduct_scores`.
- Snapshot mỗi 100 event, replay rebuild.
- Idempotency `IdempotencyMiddleware.php` + `X-Correlation-ID` `gateway.conf.template:51` qua gRPC metadata.

School chưa ES ngay, chỉ CQRS logical — ES cho School ở Phase 3c sau.

Verify: `php artisan test` handler, `go test ./internal/command`, publish `user.created` → `finance_users_sync` row, replay `event_store` OK, `grpc GetUser` đọc read-model.

---

## Phase 4 — Nginx Gateway prod spec (1d, chỉ doc, không code production/)

Viết `docs/architecture/api-gateway-prod-spec.md` cho `/home/robert/production` (repo deploy riêng):
- TLS 443 Let's Encrypt `docker/ssl`, `certbot` như `docker-compose.prod.yml:gateway`.
- JWT tại gateway: `auth_request` → `identity-grpc:50051 ValidateToken` hoặc lua `keyfunc` cache JWKS `/.well-known/jwks.json`, Go services bỏ verify lặp.
- Rate limit dual sliding window (`docs/dual_sliding_window_nginx_rate_limit.md`), `proxy_next_upstream`, retry, timeout 10s/60s giữ như template.
- Observability: `json_gateway` + `X-Correlation-ID` expose, `nginx-exporter` Prometheus `infra/helm-charts/monitoring/prometheus-values.yaml`, OTEL.

Không edit `production/` đợt này.

---

## Phase 5 — Infra & CI (1d)

- Terraform `infra/terraform/main.tf:1` + Helm `infra/helm-charts/demo` thêm `values-prod.yaml` cho 4 Go gRPC services, ResourceQuota (`323d718`).
- Ansible `infra/ansible/playbook.yml` deploy `chore/infra-devops`.
- `.woodpecker.yml` ở `production/` doc promote `chore/infra-devops → production`.

---

## Thực thi trên chore/infra-devops — commit nhỏ

```
chore(db): split finance migrations + outbox
feat(proto): add school.proto + regen vtproto
feat(grpc): add services/identity-grpc + school-grpc Go servers
feat(grpc): implement finance InvoiceService (replace Unimplemented)
feat(cqrs): CommandBus/QueryBus School+Finance pilot
feat(es): event_store + projector Identity/Finance + replay
docs(gateway): prod spec for production/ (no code)
```

Mỗi commit `git diff --stat` + `docker compose config` + `route:list` + `grpcurl` verify. Không `rm -rf`, không `--force` prod (`docs/db-rules.md`).

---

## Rủi ro & pitfalls đã gặp

- Topic binding `finance.*` vs `finance.#` làm message drop im lặng (`09-infrastructure.md:171`) — phải `#`.
- Gateway DNS stale sau recreate (`09-infrastructure.md:178`) — restart gateway.
- Base `php:8.2-fpm-alpine` đã bundle mbstring/fileinfo/OPcache (`Dockerfile:39`) — không cài lại.

---

## Checklist verify cuối

- `grpcurl list` thấy 4 services, `Login` 200, `ValidateToken` OK.
- `curl /health/identity /health/finance` 200, `X-Correlation-ID` trace qua gateway→gRPC.
- `rabbitmqadmin publish finance.invoice.paid` → notify log `[corr] nhận`, Socket.IO push.
- `php artisan test`, `go test ./...`, `docker compose up -d --build` 10 healthy.
