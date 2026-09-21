# T6.1 — RabbitMQ auth.events (queue riêng per service, DLQ, retry, idempotency, outbox, Go cache invalidation)

## Exchange & topology (docker/rabbitmq/definitions.json)
- **auth.events** — topic, durable
- Queues (quorum, DLQ=educonnect.dlx):
  - `finance_auth_events` + `.dlq`
  - `notify_auth_events` + `.dlq`
  - `school_auth_events` + `.dlq`
  - `realtime_auth_events` + `.dlq`
- Bindings: `auth.#` → mỗi queue per service
- DLQ: fanout `educonnect.dlx` → `*.dlq`

## Outbox pattern (identity DB)
- `outbox_events` (connection `identity`) + migration `2026_09_05_000002_add_auth_events_idempotency` (col `idempotency_key`)
- `AuthEventPublisher` (`app/Services/AuthEventPublisher.php`): `publish()`, helpers `roleAssigned/Revoked/permissionsChanged/userDeactivated/tokenVersionBumped`
  - envelope: `{event, version:1, correlation_id, idempotency_key, occurred_at, aggregate_id, payload}`
  - tạo row `pending` → `PublishAuthEventJob::dispatch()->afterCommit()` (outbox đảm bảo atomic với domain transaction)
- `PublishAuthEventJob` (`app/Jobs/PublishAuthEventJob.php`): exchange `auth.events`, persistent, headers `x-idempotency-key/x-correlation-id/x-event-type`, `message_id=idempotency_key`, backoff `[5,15,30,60,120]` 5 lần → `failed` → DLQ
- Tích hợp: `UserObserver::updated` (is_active/is_locked/status → token_version bump + publish `auth.user_deactivated`/`auth.token_version_bumped` + `auth.permissions_changed`), `AuthService::logoutAll` (publish), `PermissionCacheService::clearUser` (publish `auth.permissions_changed` best-effort, guard `consume.auth.events.reentry` tránh loop)

## Consumer per service — DLQ + retry + idempotency + invalidate cache
- **School PHP**: `app/Console/Commands/ConsumeAuthEvents.php` — `consume:auth-events --queue=school_auth_events` → `x-dead-letter-exchange=educonnect.dlx`, `basic_qos prefetch 10`, idempotency `SETNX auth:idempotency:{key} EX 86400` (Redis), `DEL user:{id}:permissions` qua `PermissionCacheService::clearUser` (guard reentry), nack→DLQ on error
- **Finance Go**: `services/finance/internal/auth/{cache,consumer}.go` — queue `finance_auth_events` (+.dlq), same exchange/binding, Redis injected from main, idempotency `SETNX auth:idempotency:{key} 24h`, invalidate `DEL user:{id}:permissions`
- **Notify Go**: `services/notify/internal/auth/{cache,consumer}.go` — tương tự queue `notify_auth_events`
- **Realtime**: queue `realtime_auth_events` đã khai báo sẵn trong definitions.json (consumer Node sẽ bind `auth.#`)

## Go wiring
- `services/finance/go.mod` thêm `github.com/redis/go-redis/v9`
- `services/finance/main.go`: tạo `redis.Client` → `go auth.StartAuthConsumer(ctx, rdb)` cạnh `startUserSyncConsumer`
- `services/notify/main.go`: `go auth.StartAuthConsumer(ctx, rdb)` sau khi tạo `rdb`

## Chạy
```bash
# school worker (PHP)
php artisan consume:auth-events --queue=school_auth_events
# finance & notify tự start trong main.go khi boot
docker compose up -d rabbitmq finance notify
```

## Kiểm thử idempotency/retry
- Publish cùng `idempotency_key` → consumer thứ 2 ack ngay (duplicate)
- Handler lỗi (payload JSON xấu / Redis fail) → `Nack(requeue=false)` → `educonnect.dlx` → `*.dlq` (quorum, quan sát qua mgmt http://localhost:15672)
- Outbox retry: job backoff 5 lần, `status failed` giữ `next_retry_at` 5 phút
