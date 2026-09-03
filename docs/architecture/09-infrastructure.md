# Infrastructure — API Gateway, Message Broker, Containerization

> Task 2.1 — 2.3 của microservice-split. Branch: `microservice-split`

## Tổng quan

```
                        ┌─────────────────────────────────────────────┐
 Client ──────────────► │  Nginx API Gateway (:80 / :8080 dev)        │
 (REST + WebSocket)     │  - Routing theo prefix                      │
                        │  - CORS + Rate limiting + Correlation ID    │
                        └───────┬──────────┬──────────┬───────────────┘
                                │          │          │
                /api/auth/*     │  /api/finance/*  /api/* (còn lại)
                                ▼          ▼          ▼
                        ┌───────────┐ ┌──────────┐ ┌──────────────┐
                        │ identity  │ │ finance  │ │ school       │
                        │ (Laravel  │ │ (Go Gin) │ │ (monolith    │
                        │ PHP-FPM)  │ │ :8080    │ │  Laravel)    │
                        └───────────┘ └──────────┘ └──────────────┘
                                │          │              │
                        ┌───────▼──────────▼──────────────▼───────┐
                        │  RabbitMQ cluster (3 node staging)      │
                        │  user_events / finance_events (topic)   │
                        │  notification_queue (quorum)            │
                        └───────┬───────────────────┬─────────────┘
                                │                   │
                        ┌───────▼───────┐   ┌───────▼────────┐
                        │ notify (Go)   │   │ realtime (Node)│
                        │ email/sms     │   │ Socket.IO push  │
                        └───────────────┘   └────────────────┘
```

## Task 2.1 — Nginx API Gateway

File: `nginx/nginx.conf` (main) + `nginx/gateway.conf.template` (site, envsubst)

| Route | Upstream | Kiểu |
|-------|----------|------|
| `/api/auth/*` | `identity:9000` (Laravel PHP-FPM) | fastcgi |
| `/api/finance/*` | `finance:8080` (Go) | proxy |
| `/api/*` (còn lại) | `php:9000` (monolith school) | fastcgi |
| `/socket.io/*` | `realtime:3000` (Node WS) | proxy + upgrade |
| `/health`, `/health/{identity,school,finance,notify,realtime}` | chính nó / upstream | — |

### Correlation ID (trace log)
- `nginx.conf`: `map $http_x_correlation_id $correlation_id { default $request_id; "" $request_id; }`
  → client gửi `X-Correlation-ID` thì giữ nguyên, không thì sinh bằng `$request_id`.
- Trả header `X-Correlation-ID` trong mọi response; truyền xuống upstream:
  - fastcgi: `fastcgi_param HTTP_X_CORRELATION_ID $correlation_id;`
  - proxy: `proxy_set_header X-Correlation-ID $correlation_id;`
- Log gateway (JSON) gồm `correlation_id` → ghép được request chain qua các service.

### CORS
- `map $http_origin $cors_origin` — chỉ chấp nhận origin khớp `${GATEWAY_DOMAIN}` hoặc `localhost` (envsubst).
- Preflight OPTIONS trả 204 với `Access-Control-Allow-Methods/Headers/Max-Age`.
- Header `Access-Control-Expose-Headers: X-Correlation-ID` để client đọc được ID.

### Rate limiting
| Zone | Rate | Dùng cho |
|------|------|----------|
| `gw_auth` | 30 r/m, burst 10 | `/api/auth/*` (chống brute force) |
| `gw_api` | 120 r/m, burst 30 | `/api/finance/*`, `/api/*`, `/socket.io/` |

Trả `429` khi vượt. Zone cũ (`general/api/auth`) giữ cho legacy prod config.

### Health endpoints
Gateway trả `{"status":"healthy","service":"api-gateway"}` tại `/health`; mỗi upstream có
`/health/{name}` riêng — Laravel dùng fastcgi override `REQUEST_URI=/api/health`.

## Task 2.2 — Message Broker (RabbitMQ)

File: `docker/rabbitmq/rabbitmq.conf` + `docker/rabbitmq/definitions.json`

### Topology chuẩn
| Loại | Tên | Type | Ghi chú |
|------|-----|------|---------|
| Exchange | `user_events` | topic | binding key `user.#` |
| Exchange | `finance_events` | topic | binding key `finance.#` |
| Exchange | `educonnect.dlx` | fanout | dead-letter cho message lỗi |
| Queue | `notification_queue` | **quorum** | HA native, DLX → `educonnect.dlx` |
| Queue | `notification_queue.dlq` | quorum | hàng đợi chết |
| Binding | `user.#`, `finance.#` → `notification_queue` | | |

Routing key convention:
- `user.registered`, `user.login.failed`, `user.password.reset`, `user.verified`, `user.role.changed`
- `finance.invoice.created`, `finance.invoice.paid`, `finance.invoice.overdue`, `finance.payment.received`

⚠️ Wildcard topic: `*` khớp ĐÚNG 1 từ, `#` khớp 0..n từ — event 2+ từ
(`finance.invoice.paid`) chỉ match với `finance.#`.

### Cluster
- Dev (`docker-compose.yml`): 1 node `rabbitmq-1` (management UI `:15672`).
- Staging (`docker-compose.staging.yml`): **3 node** `rabbitmq-1/2/3`,
  peer discovery qua classic config, `RABBITMQ_ERLANG_COOKIE` chung, quorum queue
  tự replicate majority (2/3 node).
- User mặc định: `educonnect` / `educonnect_dev` (hash trong definitions.json —
  sinh bằng `rabbitmqctl hash_password`).

### Consumer — notify service (Go)
`services/notify/main.go` (đã migrate từ Redis Streams):
- Retry kết nối 60s chờ broker; declare exchange/queue/bindings idempotent.
- Manual ack: email gửi OK → `Ack`; lỗi → `Nack(requeue=false)` → DLX → `notification_queue.dlq`.
- Nhận header `x-correlation-id` từ message → log trace.

### Consumer — realtime service (Node)
`services/realtime/server.js`: Socket.IO + amqplib, push message tới room
`user:{user_id}` (JWT verify trong handshake) + room `admins`.

## Task 2.3 — Containerization

| Service | Base image | Multi-stage | Non-root | Healthcheck |
|---------|-----------|-------------|----------|-------------|
| php (school, Laravel) | `php:8.2-fpm-alpine` | composer vendor → prod → **dev** (Xdebug) | `appuser` | `php-fpm -t` |
| identity (Laravel 10) | `php:8.5-fpm-alpine` | composer vendor → prod | `appuser` | `php-fpm -t` |
| finance (Go) | `golang:1.26-alpine` → `alpine:3.21` | builder | `app` (uid 10001) | wget /health |
| notify (Go) | `golang:1.26-alpine` → `alpine:3.21` | builder | `app` (uid 10001) | wget /health |
| realtime (Node) | `node:22-slim` | deps → runtime | `node` | fetch /health |

Tối ưu: `CGO_ENABLED=0` + `-ldflags="-s -w"` (Go), `npm ci --omit=dev` (Node),
`composer install --no-dev --no-scripts` + `dump-autoload --optimize` (PHP),
ca-certificates + tzdata, giới hạn resource trong compose staging.

### Compose
- `docker-compose.yml` — **Dev**: bind-mount source, Xdebug, expose MySQL/Redis/RabbitMQ/Mailpit, gateway `:8080`.
- `docker-compose.staging.yml` — **Staging**: build production, không bind-mount,
  RabbitMQ cluster 3 node, không expose DB/broker ra ngoài, gateway `:80/:443`.

## Kết quả verify E2E (2026-08-02, dev stack)

| Kiểm tra | Kết quả |
|---|---|
| 10 container up + healthy | ✅ |
| `/health/identity` → identity-service | ✅ |
| `/health/school`, `/api/health` → monolith | ✅ |
| `/api/auth/login` (sai mật khẩu) → 401 Invalid credentials | ✅ |
| `X-Correlation-ID` sinh + expose + log JSON | ✅ |
| RabbitMQ: 2 exchange topic + DLX + 2 consumer bám notification_queue | ✅ |
| Publish `finance.invoice.paid` → notify consume (`[corr] nhận`) | ✅ |
| Publish → realtime push Socket.IO `notification` tới room user:1 | ✅ |
| Rate limit gw_auth (burst 10): 10x 401 + 5x 429 | ✅ |

## Verify nhanh

```bash
# Dev
docker compose up -d --build
curl -i http://localhost:8080/api/health                 # school qua gateway + X-Correlation-ID
curl -i http://localhost:8080/health/identity            # identity Laravel
curl -i http://localhost:8080/api/finance/health         # finance Go (public)
curl -i -X OPTIONS http://localhost:8080/api/auth/login  # CORS preflight

# RabbitMQ topology
docker exec educonnect-dev-rabbitmq-1 rabbitmqctl list_exchanges name type
docker exec educonnect-dev-rabbitmq-1 rabbitmqctl list_queues name type messages
docker exec educonnect-dev-rabbitmq-1 rabbitmqctl list_bindings source destination routing_key

# Publish thử → notify consume (log) → realtime push
docker exec educonnect-dev-rabbitmq-1 rabbitmqadmin publish \
  exchange=finance_events routing_key=finance.invoice.paid \
  payload='{"type":"email","to":"test@edu.vn","subject":"Hóa đơn","body":"...","user_id":1}'

# E2E realtime (Socket.IO client + publish 4 msg — round-robin 2 consumer)
cd services/realtime && node test/consumer.test.js http://localhost:8080

# Cluster (staging)
docker compose -f docker-compose.staging.yml up -d rabbitmq-1 rabbitmq-2 rabbitmq-3
docker exec educonnect-staging-rabbitmq-1 rabbitmqctl cluster_status
```

## Pitfalls đã gặp (quan trọng)

1. **Topic binding wildcard**: `finance.*`/`user.*` chỉ match ĐÚNG 1 từ — event thực tế
   (`finance.invoice.paid`, `user.password.changed`) không bao giờ tới queue → message
   bị drop im lặng (exchange unroutable), queue luôn 0 msg, consumer không log gì.
   Fix: dùng `finance.#`/`user.#` ở **cả 3 nơi**: `definitions.json`, `services/notify/main.go`,
   `services/realtime/server.js`.
2. **Gateway upstream hostname stale**: nginx resolve `identity:9000`/`php:9000` 1 lần lúc
   startup; sau khi backend container bị recreate (đổi IP), Docker gán lại IP cũ cho container
   khác → gateway gọi nhầm service (log `upstream_addr` thấy IP sai, response "swap").
   Fix: `docker compose restart gateway`.
3. **Base php:8.5-fpm-alpine đã bundle mbstring/fileinfo/Zend OPcache** — thêm chúng vào
   `docker-php-ext-install` gây `cp: can't stat 'modules/*'` (ext "already loaded").
   Bỏ khỏi list install.
4. **2 consumer cùng queue = round-robin**: test publish 1 message có thể rơi vào consumer
   kia; test nên publish nhiều message (xem `services/realtime/test/consumer.test.js`).
