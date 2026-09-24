# API Gateway — Prod Spec (Phase 4) — chore/infra-devops

> **Scope:** spec cho repo deploy riêng `/home/robert/production` (`.woodpecker`, `docker`, `nginx`). **Phase 4 chỉ doc, không code `production/`** (`docs/microservices/plan-chore-infra-devops-grpc-cqrs.md:123`). Branch hiện tại `chore/infra-devops`. File này là draft để `production/` copy khi promote.
> **Upstream template:** `nginx/gateway.conf.template:21` + `nginx/nginx.conf:1` + `docker-compose.prod.yml:1`. Giữ nguyên `keepalive 32` cho mọi upstream và healthcheck `redis`/`rabbitmq` như prod compose.

---

## 1. Hiện trạng (evidence)

| Item | Evidence | Ghi chú |
|------|----------|---------|
| Routing | `gateway.conf.template:4-8` `/api/auth→identity:9000` fastcgi, `/api/finance→finance:8080` proxy, `/api/*→php:9000` fastcgi, `/socket.io→realtime:3000` proxy | Order `location /api/auth/` + `/api/finance/` trước `/api/` để longest-prefix match. Prod giữ nguyên order. |
| Upstreams | `gateway.conf.template:22-26` `identity_svc php school_svc finance_svc notify_svc realtime_svc` | **Tất cả `keepalive 32`** — constraint của task. Không giảm. `nginx.conf` tuning `worker_connections 4096`, `keepalive_timeout 65` giữ nguyên. |
| Log | `gateway.conf.template:29` `log_format json_gateway escape=json` + `nginx.conf:24` `map $http_x_correlation_id $correlation_id` | `correlation_id` = `$request_id` nếu client không gửi. `add_header X-Correlation-ID $correlation_id always` + `proxy_set_header`/`fastcgi_param HTTP_X_CORRELATION_ID` truyền downstream. Prod giữ `json_gateway` làm default, không đổi sang `json_combined`. |
| Rate limit | `nginx.conf:96-97` `gw_auth 30r/m burst10`, `gw_api 120r/m burst30` + `gateway.conf.template:115,131,165,192` `limit_req zone=... burst... nodelay` | `limit_req_status 429` (`nginx.conf:99`). Zone `general/api/auth` giữ cho `default.conf.template` legacy. |
| CORS | `gateway.conf.template:15-19` `map $http_origin $cors_origin` envsubst `${GATEWAY_DOMAIN}` + localhost | Preflight `OPTIONS 204` ở mỗi `location /api/*`. `fastcgi_hide_header ACAO` để nginx là nguồn CORS duy nhất. Prod không đổi. |
| Health | `gateway.conf.template:71-111` `/health` + `/health/{identity,school,finance,notify,realtime}` | FastCGI health override `REQUEST_URI /api/health` cho Laravel. Proxy health cho Go/Node. |
| Prod compose | `docker-compose.prod.yml:16` `gateway` nginx:1.25-alpine, `80:80 443:443`, volumes `nginx.conf`, `gateway.conf.template`→`/etc/nginx/templates/`, `docker/ssl:/etc/nginx/ssl:ro`, `gateway_logs:/var/log/nginx`, `depends_on php/identity:service_healthy` | `php`/`identity` build target `production` (`docker/php/Dockerfile`). 3 MySQL `mysql-identity|school|finance` + `redis maxmemory 256mb allkeys-lru` + `rabbitmq-1/2/3` cluster + `horizon` + `scheduler` + `certbot` loop `certbot renew; sleep 12h`. |
| Redis health | `docker-compose.prod.yml:363` `redis: test CMD redis-cli ping interval10s retries3` | Compose dev cũng có (`docker-compose.yml:356`). Prod giữ, không expose port. |
| RabbitMQ health | `docker-compose.prod.yml:388` `rabbitmq-1/2/3: test CMD rabbitmq-diagnostics -q ping interval15s retries5 start_period30s/45s` + `rabbitmq.conf:6` `management.load_definitions` + `definitions.json` quorum `notification_queue` DLX `educonnect.dlx` topic `user.# finance.#` (`09-infrastructure.md:75`) | Pitfall `finance.*` chỉ match 1 word — phải `#`. |
| TLS dev | `gateway.conf.template:44` chỉ `listen 80` (dev `:8080`). `default.conf.template:14,30` đã có mẫu TLS `443 ssl http2` + `ssl_certificate /etc/nginx/ssl/live/${NGINX_HOST}/fullchain.pem` + `ssl_protocols TLSv1.2 TLSv1.3` + `ssl_ciphers` Mozilla Intermediate + `ssl_stapling on` + `resolver 1.1.1.1`. | Gateway prod thiếu TLS — cần spec phần 2. |
| Infra | `infra/terraform/main.tf:1` `kubernetes_namespace educonnect` + `ResourceQuota 4Gi/6Gi pods20` + `helm_release ingress-nginx 4.10.0 NodePort`; `infra/helm-charts/demo: replicaCount1 image nginxdemos/hello`; `infra/helm-charts/monitoring/prometheus-values.yaml` kube-prometheus-stack 700MiB-1.2Gi; `infra/ansible/playbook.yml` compose up only `project_src /home/robert/production/docker` `build: never` | Staging `docker-compose.staging.yml` 3-node RabbitMQ, gateway `:443`. |
| JWKS | `docs/jwt-rs256-jwks.md:8` RS256 2048 `jwt-private.pem` + `jwt-public.pem`, `GET /.well-known/jwks.json` cache 3600s, `finance/go.mod: MicahParks/keyfunc v3.8.1` + `golang-jwt/jwt v5.3.1` | Go services hiện `AUTH_JWKS_URL http://gateway/.well-known/jwks.json`. Gateway chưa verify JWT. |

---

## 2. TLS — Let's Encrypt certbot (mẫu `default.conf.template`, áp cho gateway)

### 2.1 Mục tiêu
- `gateway.conf.template` prod lắng `80` + `443 ssl http2`, `http://` chỉ serve `/.well-known/acme-challenge/` + `301 https://$host$request_uri` (copy `default.conf.template:13-27`).
- `ssl_certificate /etc/nginx/ssl/live/${NGINX_HOST}/fullchain.pem`, `ssl_certificate_key .../privkey.pem`, `ssl_trusted_certificate .../chain.pem` (`docker/ssl` mount RO như `docker-compose.prod.yml:27`).
- `ssl_protocols TLSv1.2 TLSv1.3`, `ssl_ciphers ECDHE-...` Mozilla Intermediate (`default.conf.template:44-45`), `ssl_prefer_server_ciphers off`, `ssl_session_cache shared:SSL:50m`, `ssl_session_timeout 1d`, `ssl_session_tickets off` giữ từ `nginx.conf:103`.
- `ssl_stapling on; ssl_stapling_verify on; resolver 1.1.1.1 8.8.8.8 valid=300s; resolver_timeout 5s;` + `add_header Strict-Transport-Security "max-age=63072000; includeSubDomains; preload" always;` (`default.conf.template:64`).

### 2.2 Certbot
- Giữ `docker-compose.prod.yml:528` `certbot` image `certbot/certbot:latest`, volumes `./docker/ssl:/etc/letsencrypt`, `certbot_www:/var/www/certbot`, entrypoint loop `certbot renew; sleep 12h`.
- Lần đầu manual: `docker compose -f docker-compose.prod.yml run --rm certbot certonly --webroot -w /var/www/certbot -d ${NGINX_HOST} -d www.${NGINX_HOST}` (comment sẵn `docker-compose.prod.yml:534`).
- Cron renew đã trong loop; thêm fallback `0 12 * * * docker compose -f docker-compose.prod.yml run --rm certbot renew --quiet && docker compose exec gateway nginx -s reload` ghi vào `production/docker/scripts` khi copy.
- Gateway template thêm:
```nginx
# HTTP — ACME + redirect
server {
    listen 80; listen [::]:80;
    server_name ${NGINX_HOST} www.${NGINX_HOST};
    location /.well-known/acme-challenge/ { root /var/www/certbot; }
    location / { return 301 https://$host$request_uri; }
}
server {
    listen 443 ssl; listen [::]:443 ssl; http2 on;
    server_name ${NGINX_HOST} www.${NGINX_HOST};
    ssl_certificate     /etc/nginx/ssl/live/${NGINX_HOST}/fullchain.pem;
    ssl_certificate_key /etc/nginx/ssl/live/${NGINX_HOST}/privkey.pem;
    # ... như default.conf.template:44-56
```
- Verify: `docker compose -f docker-compose.prod.yml config` thấy `gateway` mount `docker/ssl:ro` + `certbot_www`; `docker exec gateway nginx -t` OK; `curl -vik https://$NGINX_HOST/health` chain valid; `openssl s_client -connect $NGINX_HOST:443 -servername $NGINX_HOST | grep "Verify return code: 0"`.

### 2.3 Env
- `.env.production` thêm `NGINX_HOST=api.toanrobert.online`, `GATEWAY_DOMAIN=api.toanrobert.online` (envsubst `gateway.conf.template:18`). Không commit private key; `storage/certs/jwt-private.pem` 600 vẫn gitignored như `jwt-rs256-jwks.md:9`.

---

## 3. WAF

### 3.1 Lựa chọn
- **Không build ModSecurity WAF trong `nginx:1.25-alpine` image** (nặng, cần compile). Prod dùng **2 lớp**: (a) nginx native hardening + (b) Cloudflare WAF ở edge (đã có `DEVOPS-PLAN.md` Cloudflare Tunnel). Nếu cần in-container WAF thì đổi sang `owasp/modsecurity-crs:nginx-alpine` ở `production/docker/nginx/Dockerfile` riêng, không đụng `educonnect/nginx`.
- Lớp (a) — giữ và mở rộng `gateway.conf.template`:
  - `client_max_body_size 100M` đã có `nginx.conf:59` (giữ, finance upload).
  - Block sensitive: `location ~ /\.(?!well-known) { deny all; return 404; }` + `location ~ ^/(\.env|\.git|\.htaccess|storage|bootstrap/cache) { deny all; return 404; }` (copy `default.conf.template:68-76` vào gateway server block `/`).
  - Thêm `limit_conn addr 20;` ở `/api/` như `default.conf.template:112` nếu cần, nhưng `addr` zone đã `limit_conn_zone $binary_remote_addr zone=addr:10m` (`nginx.conf:100`).
  - Header: giữ `X-Frame-Options SAMEORIGIN`, `X-Content-Type-Options nosniff`, `X-XSS-Protection`, `Referrer-Policy`, `Permissions-Policy`; thêm `Content-Security-Policy` chỉ khi gateway serve frontend (hiện API-only → không thêm).
  - Rate limit đã là WAF lớp 1.
- Lớp (b): Cloudflare Managed Rules (OWASP), Bot Fight, `CF-Connecting-IP` làm `real_ip_header` đã spec trong `dual_sliding_window_nginx_rate_limit.md:583` — khi enable CF thì `nginx.conf` thêm block `set_real_ip_from` CF ranges + `real_ip_header CF-Connecting-IP; real_ip_recursive on;`.

### 3.2 Logging WAF
- `429` log `limit_req_status` + `connection` log; WAF deny log `access_log` `json_gateway` thêm field `waf_block` nếu deploy ModSecurity.

---

## 4. JWKS — `auth_request` tại gateway (MicahParks/keyfunc pattern, không verify lặp ở Go)

### 4.1 Mục tiêu
- Gateway verify JWT RS256 **trước** khi proxy/fastcgi tới `identity/school/finance`, dùng `/.well-known/jwks.json` của `identity` (cache 3600s `jwt-rs256-jwks.md:21`). Go finance/notify hiện dùng `MicahParks/keyfunc v3.8.1` + `golang-jwt/jwt v5` — sau khi gateway verify thì **bỏ verify lặp** ở finance internal routes, chỉ verify ở `auth_request` + propagate `X-User-Id` header; giữ verify ở Go cho inter-service gRPC direct call.

### 4.2 Option A — `auth_request` → `identity` HTTP (không cần lua, deploy ngay)
- Internal location `/internal/auth` proxy tới `identity_svc` validate:
```nginx
# auth_request dùng internal endpoint của identity (Laravel ValidateToken)
location = /internal/auth {
    internal;
    proxy_pass http://identity_svc/api/auth/validate;  # hoặc /api/jwks/verify — tuỳ identity route
    proxy_pass_request_body off;
    proxy_set_header Content-Length "";
    proxy_set_header X-Original-URI $request_uri;
    proxy_set_header Authorization $http_authorization;
    proxy_set_header X-Correlation-ID $correlation_id;
}
# Áp cho /api/ protected (không áp /api/auth/login|register|refresh, /health, /.well-known/, /socket.io handshake)
location /api/ {
    auth_request /internal/auth;
    auth_request_set $auth_user_id $upstream_http_x_user_id;  # identity trả X-User-Id
    proxy_set_header X-User-Id $auth_user_id;  # hoặc fastcgi_param HTTP_X_USER_ID
    error_page 401 = @unauthorized;
    error_page 403 = @forbidden;
    # ... limit_req + proxy/fastcgi như cũ
    # timeout giữ 10s/60s (gateway.conf.template:151-153, 185-187)
}
location @unauthorized { add_header Content-Type application/json; return 401 '{"error":"unauthorized","message":"Invalid or expired token"}'; }
```
- Pros: không cần build `nginx-lua`/`njs`, tái dùng logic `JwksController` sẵn có. Cons: thêm 1 hop RTT tới `identity` mỗi request (keepalive 32 giảm cost).

### 4.3 Option B — lua-resty-openidc / njs keyfunc cache (khuyên dùng cho prod khi scale)
- Build `nginx:1.25-alpine` với `lua-resty-jwt` hoặc `njs` + `keyfunc` cache JWKS in-memory 5m, verify `iss=${JWT_ISS}`, `aud=${JWT_AUD}`, `kid` rotation grace. `AUTH_JWKS_URL http://gateway/.well-known/jwks.json` nội bộ qua `identity_svc`. Khi deploy, Go services giữ `keyfunc` cache cho gRPC inter-service, gateway cho HTTP ingress.
- Spec file `production/nginx/conf.d/jwks-cache.conf` (không thuộc repo này): `lua_shared_dict jwks 1m; init_by_lua_file /etc/nginx/lua/init.lua;` Mở rộng khi `production/` copy spec.

### 4.4 Áp dụng
- Prod phase 4 **chốt Option A** (auth_request tới identity) để không đổi image nginx; ghi Option B làm roadmap.
- Miễn verify: `location = /api/auth/login`, `/api/auth/register`, `/api/auth/refresh`, `GET /.well-known/jwks.json`, `GET /.well-known/openid-configuration`, `GET /health*`, `location /.well-known/acme-challenge/` (TLS).
- Truyền `X-User-Id` + `X-Correlation-ID` xuống tất cả upstream; Go services tin header này khi request qua gateway (đặt `TRUST_GATEWAY=true` env).

---

## 5. Rate limit — dual sliding window

### 5.1 Nginx layer (edge)
- Giữ `nginx.conf:96-97` `gw_auth 30r/m burst10 nodelay`, `gw_api 120r/m burst30 nodelay` + `limit_req_status 429`. Đây là **fixed window** `limit_req` chống burst/DoS.
- Thêm `limit_conn addr 20` ở `/api/` nếu cần anti-concurrent (đã có zone `addr:10m`).
- Header `Retry-After` + custom `error_page 429 @rate_limit_exceeded` trả `{"message":"Too many requests. Please slow down.","retry_after":60}` (`dual_sliding_window_nginx_rate_limit.md:783`).

### 5.2 App layer (sliding window Redis Lua — `docs/dual_sliding_window_nginx_rate_limit.md`)
- **Login brute-force:** dual key `educonnect:auth:rate:ip:{ip}` (30r/m) + `educonnect:auth:rate:login:{ip}:{email}` (10r/m) ZSET sliding window 60s, lua atomic `ZREMRANGEBYSCORE + ZCARD + ZADD + EXPIRE` (`dual_sliding_window_nginx_rate_limit.md:41-79`). Đã implement concept `AuthService.php` trong doc — identity áp nguyên khi tách `identity-grpc`.
- **Generic API:** `RateLimitService::check(identifier, maxAttempts, windowSeconds)` với `identifier` = `ip` hoặc `user:id` (`dual_sliding_window_nginx_rate_limit.md:434`), keys `educonnect:ratelimit:{type}:{md5}`.
- Redis health `redis: redis-cli ping` (`docker-compose.prod.yml:364`) bắt buộc cho sliding window — monitor `KEYS educonnect:rate:*`, `ZRANGE ... WITHSCORES`.
- Cấu hình khuyến nghị cho prod (sau khi gateway verify JWT thì `identifier` đổi sang `user:{id}` cho `/api/*`):
  | Endpoint | App limit | Nginx zone |
  |----------|-----------|------------|
  | `POST /api/auth/login` | `ip:30r/m + ip+email:10r/m` (dual) | `gw_auth 30r/m burst10` |
  | `POST /api/auth/register` | `3r/h per ip` | `gw_auth` |
  | `POST /api/auth/refresh` | `10r/m per ip` | `gw_auth` |
  | `GET /api/*` (protected) | `60r/m per user` | `gw_api 120r/m burst30` |
  | `/socket.io/*` | `gw_api 120r/m` | `gw_api` |

### 5.3 Verify
- `for i in {1..15}; do curl -X POST https://$NGINX_HOST/api/auth/login -H "Content-Type: application/json" -d '{"email":"test@edu.vn","password":"wrong"}' -w "%{http_code}\n" ; done` — 10x 401/429, 5x 429.
- `docker exec redis redis-cli ZRANGE educonnect:auth:rate:ip:1.2.3.4 0 -1 WITHSCORES`.
- `docker exec gateway tail -f /var/log/nginx/gateway.access.log | jq '.status==429'`.

---

## 6. Observability — OTEL + json_gateway + Prometheus

### 6.1 Logging
- Giữ `log_format json_gateway escape=json` (`gateway.conf.template:29`) fields `time, correlation_id, remote_addr, method, uri, status, response_time, upstream_time, upstream_addr, user_agent`. Prod thêm field `host`, `x_forwarded_for`, `request_id` nếu cần nhưng không đổi format hiện tại.
- `access_log /var/log/nginx/gateway.access.log json_gateway` + `error_log warn` (`gateway.conf.template:48-49`).
- Truyền `X-Correlation-ID` toàn chain: gateway → `identity` fastcgi `HTTP_X_CORRELATION_ID`, → `finance` proxy `X-Correlation-ID`, → RabbitMQ header `x-correlation-id` (`09-infrastructure.md:104`), → `notify`/`realtime` log.

### 6.2 OTEL
- Thêm `otel` sidecar hoặc `nginx-module-otel` khi build gateway image riêng cho prod (không trong `nginx:1.25-alpine` vanilla). Spec prod:
  - `opentelemetry-collector` service trong `docker-compose.prod.yml` (chưa có — ghi TODO khi copy sang `production/docker/docker-compose.yml`).
  - Env `OTEL_EXPORTER_OTLP_ENDPOINT=http://otel-collector:4317`, `OTEL_SERVICE_NAME=api-gateway`, `OTEL_TRACES_SAMPLER=parentbased_traceidratio ratio 0.1`.
  - Nginx inject `traceparent` header: `proxy_set_header traceparent $opentelemetry_traceparent;` khi module sẵn.
- Tạm thời **chưa thêm OTEL** trong repo này — chỉ spec để `production/` bật khi có collector. Verify bằng `curl -H "traceparent: 00-..." $NGINX_HOST/api/health` log `correlation_id` mapping.

### 6.3 Prometheus
- `infra/helm-charts/monitoring/prometheus-values.yaml` đã có `kube-prometheus-stack` 3d retention 2GB, scrape 30s. Thêm `nginx-exporter` (hoặc `ingress-nginx` metrics) khi deploy K8s:
```yaml
# production/infra/helm-charts/gateway/values.yaml (draft)
metrics:
  enabled: true
  serviceMonitor:
    enabled: true
    interval: 30s
```
- Docker Compose monitoring đã có `docker/prometheus`, `grafana` trong `production/docker/monitoring` — gateway expose `/metrics` via `nginx-vts` hoặc `stub_status` khi cần.

---

## 7. Upstream tuning — keepalive 32 + timeout + retry

- **Giữ nguyên** `upstream { server ...; keepalive 32; }` cho `identity_svc, school_svc, finance_svc, notify_svc, realtime_svc` (`gateway.conf.template:22-26`).
- `fastcgi_keep_conn on` + `fastcgi_buffers 16 16k; fastcgi_buffer_size 32k;` (`gateway.conf.template:148-150`, `nginx.conf:58-63`) giữ nguyên.
- Timeouts giữ như template: `fastcgi_connect_timeout 10s; fastcgi_send_timeout 60s; fastcgi_read_timeout 60s;` + `proxy_connect_timeout 10s; proxy_send_timeout 60s; proxy_read_timeout 60s;` (`gateway.conf.template:151-153,185-187`). `proxy_read_timeout 3600s` riêng cho `/socket.io/` (`gateway.conf.template:125`).
- **Retry:** thêm `proxy_next_upstream error timeout http_502 http_503 http_504; proxy_next_upstream_timeout 10s; proxy_next_upstream_tries 2;` cho `/api/finance/` và `/socket.io/` (idempotent GET). Không bật cho `POST /api/auth/*` (non-idempotent).
- `keepalive_timeout 65; keepalive_requests 1000;` (`nginx.conf:53-54`) giữ nguyên.

---

## 8. Redis / RabbitMQ healthcheck (bắt buộc giữ)

- **Redis:** `redis:7-alpine` `command maxmemory 256mb allkeys-lru appendonly yes appendfsync everysec` + `healthcheck test CMD redis-cli ping interval10s timeout5s retries3` (`docker-compose.prod.yml:344-368`). Không expose port prod. Log `maxmemory` alert khi `used_memory > 200mb`.
- **RabbitMQ:** 3-node `rabbitmq:3.13-management-alpine` `hostname rabbitmq-1/2/3`, `RABBITMQ_ERLANG_COOKIE` chung, `rabbitmq.conf: cluster_formation.peer_discovery_backend=rabbit_peer_discovery_classic_config` + `definitions.json` topic `user_events finance_events` binding `user.# finance.#` → `notification_queue` quorum DLX `educonnect.dlx` (`09-infrastructure.md:72`). Health `rabbitmq-diagnostics -q ping interval15s timeout10s retries5 start_period30s/45s` (`docker-compose.prod.yml:388-446`). `depends_on rabbitmq-1:service_healthy` cho node 2/3. Pitfall: `finance.*` drop im lặng — phải `#`.

---

## 9. Infra — Terraform

- `infra/terraform/main.tf:1` đã có `kubernetes_namespace educonnect` + `ResourceQuota requests.memory 4Gi limits.memory 6Gi pods20` + `kubernetes_namespace ingress_nginx` + `helm_release nginx_ingress 4.10.0 NodePort`.
- Prod spec thêm (khi copy sang `production/infra/terraform`):
  - `terraform.required_version >=1.0`, `required_providers kubernetes ~>2.30 helm ~>2.13` (`infra/terraform/providers.tf:1`).
  - Biến `var.kubeconfig`, `var.ingress_class`, `var.acme_email` cho Let's Encrypt.
  - `helm_release cert-manager` nếu K8s dùng ClusterIssuer thay vì `certbot` container.
  - `output educonnect_namespace`, `nginx_ingress_status` giữ (`infra/terraform/outputs.tf`).
- Verify: `terraform fmt -check; terraform validate; terraform plan` (không `apply` trên prod khi chưa approve `docs/db-rules.md`).

---

## 10. Helm — `infra/helm-charts/demo` + `values-prod.yaml` (draft)

- `infra/helm-charts/demo/Chart.yaml: apiVersion v2 name demo version 0.1.0`; `values.yaml: replicaCount1 image nginxdemos/hello`; `values-dev.yaml: replicaCount2`; `templates/deployment.yaml + service.yaml` cơ bản (`infra/helm-charts/demo/templates/`).
- Prod thêm `infra/helm-charts/demo/values-prod.yaml` (spec, chưa commit `production/`):
```yaml
replicaCount: 3
image:
  repository: ghcr.io/educonnect/demo
  tag: "1.0.0"
  pullPolicy: IfNotPresent
service:
  type: ClusterIP
  port: 80
ingress:
  enabled: true
  className: nginx
  annotations:
    cert-manager.io/cluster-issuer: letsencrypt-prod
    nginx.ingress.kubernetes.io/rate-limit: "120"
  hosts:
    - host: api.toanrobert.online
      paths: [{ path: /, pathType: Prefix }]
  tls:
    - secretName: api-tls
      hosts: [api.toanrobert.online]
resources:
  limits: { cpu: 200m, memory: 256Mi }
  requests: { cpu: 100m, memory: 128Mi }
autoscaling:
  enabled: true
  minReplicas: 2
  maxReplicas: 5
  targetCPUUtilizationPercentage: 70
```
- Khi tách 4 Go gRPC services (`services/identity-grpc :50051, school-grpc :50053, finance :50051, notify :50052` — `plan-chore-infra-devops:81`), mỗi service có chart riêng copy từ `demo` + `grpc` port + `livenessProbe /health` + `readinessProbe` + `hpa`.
- Verify: `helm lint infra/helm-charts/demo -f infra/helm-charts/demo/values-prod.yaml; helm template demo infra/helm-charts/demo -f values-prod.yaml | grep -A2 kind`.

---

## 11. Ansible — deploy prod

- `infra/ansible/playbook.yml:1` + `roles/deploy/tasks/main.yml:8` `community.docker.docker_compose_v2 project_src=/home/robert/production/docker state=present pull=missing build=never recreate=auto remove_orphans wait timeout120` + `docker ps` verify không `unhealthy/exited` (`infra/ansible/playbook.yml:11` doc ghi chỉ `compose up` không provision, không chạm `.env` hay `data/*`).
- `infra/ansible/inventory.yml: localhost ansible_connection=local`; `ansible.cfg: inventory=inventory.yml roles_path=roles`.
- Prod deploy flow (spec cho `production/` — không chạy trong repo này):
```bash
cd /home/robert/educonnect/infra/ansible
ansible-galaxy collection install -r requirements.yml  # community.docker >=5.0.0
ansible-playbook playbook.yml --check --diff          # bắt buộc trước
ansible-playbook playbook.yml                          # chủ nhân tự chạy, không CI tự động
```
- Constraints: `project_src` PHẢI là `/home/robert/production/docker` vì `.env`, `./data`, `./redis.conf` là path tương đối từ đó (`roles/deploy/tasks/main.yml:4` comment). Không `rm -rf`, không `--force` prod.

---

## 12. Checklist Phase 4 (1d, chỉ doc)

- [ ] `nginx/gateway.conf.template` spec TLS 443 + ACME `/.well-known/acme-challenge/` + HSTS + OCSP (copy mẫu `default.conf.template:13-64`) — draft xong trong file này.
- [ ] `nginx/nginx.conf` giữ `limit_req_zone gw_auth/gw_api`, `limit_req_status 429`, `log_format json_gateway`, `map $correlation_id`, `keepalive 32` upstream.
- [ ] JWKS `auth_request /internal/auth` → `identity_svc` (Option A) miễn `/api/auth/login|register|refresh`, `/.well-known/*`, `/health*`, propagate `X-User-Id`.
- [ ] Dual sliding window: Nginx `gw_auth/gw_api` + app Redis Lua `educonnect:auth:rate:*` + `dual_sliding_window_nginx_rate_limit.md` giữ nguyên, verify 429.
- [ ] `proxy_next_upstream error timeout http_502/503/504` + `3600s` cho `/socket.io/`, timeout `10s/60s` giữ.
- [ ] OTEL collector spec (TODO `production/docker/docker-compose.yml`), Prometheus `nginx-exporter` + `prometheus-values.yaml` scrape 30s.
- [ ] `docker-compose.prod.yml` giữ `gateway` mount `docker/ssl:ro`, `certbot` loop, `redis/healthcheck ping`, `rabbitmq-1/2/3 healthcheck ping interval15s`, `depends_on service_healthy`.
- [ ] Terraform `main.tf` ResourceQuota + ingress-nginx, `providers.tf` + `outputs.tf`, `terraform validate`.
- [ ] Helm `demo/values-prod.yaml` replica3 + ingress TLS + hpa, `helm lint/template`.
- [ ] Ansible `playbook.yml --syntax-check`, `--check --diff`, `community.docker` collection.
- [ ] Không commit `production/` — chỉ `docs/architecture/api-gateway-prod-spec.md`.

---

## 13. Lệnh verify (không đụng prod)

```bash
# Branch + status
git -C /home/robert/educonnect branch --show-current  # chore/infra-devops
git -C /home/robert/educonnect diff --stat | head -n 30

# Compose validate
docker compose -f docker-compose.prod.yml config | grep -A3 gateway
docker compose -f docker-compose.yml config | grep -A3 gateway
docker compose -f docker-compose.prod.yml config | grep -E "healthcheck|keepalive|redis|rabbitmq"

# Nginx syntax (trong container gateway khi up)
docker compose up -d gateway --build
docker exec educonnect-gateway-1 nginx -t
docker exec educonnect-gateway-1 nginx -T | grep -E "limit_req_zone|log_format json_gateway|keepalive 32|correlation_id"
docker exec educonnect-gateway-1 cat /etc/nginx/conf.d/gateway.conf | head -n 60  # envsubst result

# Health + correlation
curl -i http://localhost:8080/health
curl -i http://localhost:8080/health/identity
curl -i http://localhost:8080/health/finance
curl -i http://localhost:8080/api/health | grep -i X-Correlation-ID
curl -i -H "X-Correlation-ID: test-corr-123" http://localhost:8080/api/health | grep -i X-Correlation-ID

# Rate limit
for i in $(seq 1 15); do curl -s -o /dev/null -w "%{http_code}\n" -X POST http://localhost:8080/api/auth/login -H "Content-Type: application/json" -d '{"email":"a@edu.vn","password":"x"}'; done | sort | uniq -c  # 10x 401 + 5x 429

# Redis / RabbitMQ health
docker exec educonnect-dev-redis-1 redis-cli ping
docker exec educonnect-dev-rabbitmq-1 rabbitmq-diagnostics -q ping
docker exec educonnect-dev-rabbitmq-1 rabbitmqctl list_exchanges name type | grep user_events
docker exec educonnect-dev-rabbitmq-1 rabbitmqctl list_bindings source destination routing_key | grep -E "user.#|finance.#"

# Terraform / Helm / Ansible syntax
terraform -chdir=infra/terraform fmt -check
terraform -chdir=infra/terraform validate  # cần kubeconfig, lỗi thiếu thì expected
helm lint infra/helm-charts/demo
helm template demo infra/helm-charts/demo -f infra/helm-charts/demo/values.yaml | head -n 40
ansible-playbook infra/ansible/playbook.yml --syntax-check
ansible-playbook infra/ansible/playbook.yml --check --diff  # no change khi dry-run

# JWKS
curl -s http://localhost:8080/.well-known/jwks.json | jq '.keys | length'  # 2 (RSA + Ed25519)
curl -s http://localhost:8080/.well-known/openid-configuration | jq .

# TLS (khi prod đã có cert)
# docker compose -f docker-compose.prod.yml run --rm certbot certonly --webroot -w /var/www/certbot -d api.toanrobert.online --dry-run
# echo | openssl s_client -connect api.toanrobert.online:443 -servername api.toanrobert.online 2>/dev/null | openssl x509 -noout -dates
```

---

## 14. Files tạo / không tạo

- **Tạo (repo này, branch `chore/infra-devops`):** `docs/architecture/api-gateway-prod-spec.md` (file này) — draft spec duy nhất.
- **Không tạo:** bất kỳ file nào trong `/home/robert/production/` (`.woodpecker/docker/nginx/...`). Promote copy bằng tay khi `production` approve.
- **Tham chiếu giữ nguyên:** `nginx/gateway.conf.template:22 keepalive 32`, `nginx/nginx.conf:96 gw_auth/gw_api`, `docker-compose.prod.yml:344 redis healthcheck`, `docker-compose.prod.yml:388 rabbitmq healthcheck`, `infra/terraform/main.tf:1`, `infra/helm-charts/demo/values.yaml`, `infra/ansible/playbook.yml:1`.

> Next step sau Phase 4: copy spec này sang `production/README-DEVOPS.md` + `production/nginx/gateway.prod.conf.template` + `production/infra/terraform` + `production/infra/helm-charts` khi owner `chore/infra-devops → production` promote, kèm `helm values-prod.yaml` + `certbot --dry-run` + `terraform plan` verify.
