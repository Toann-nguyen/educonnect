User → Cloudflare Edge (TLS, DDoS, Cache)
           ↓  Tunnel (mã hóa)
       cloudflared daemon (chạy trong Docker)
           ↓
       Nginx container (port 80, nội bộ)
           ↓
       PHP-FPM container (Laravel app)

Nginx vẫn cần thiết — không phải để thay thế Tunnel, mà là 2 lớp khác nhau:
1. **Cloudflare Tunnel**: Đưa traffic từ internet công cộng về server local một cách an toàn mà không cần mở port trên Router.
2. **Nginx**: Phục vụ static files trực tiếp, định tuyến request PHP đến PHP-FPM, quản lý client_max_body_size, và thiết lập headers bảo mật.

---

# 🏗️ BẢN THIẾT KẾ HỆ THỐNG: DỰ ÁN EDUCONNECT (MÔI TRƯỜNG PRODUCTION)

## PHẦN 1: QUY HOẠCH KIẾN TRÚC TÊN MIỀN & LƯU TRỮ

Hệ thống được chia làm 2 thành phần độc lập (Micro-frontend & API-first), giao tiếp qua giao thức HTTPS bảo mật:

| Thành phần | Công nghệ | Nơi lưu trữ (Host) | Tên miền truy cập |
| :--- | :--- | :--- | :--- |
| **Frontend (Giao diện)** | React (Vite) | **Cloudflare Pages** | `https://toanrobert.online` |
| **Backend (Xử lý API)** | Laravel + MySQL | **Máy Arch Linux (Tại nhà)** | `https://api.toanrobert.online` |

---

## PHẦN 2: LOGIC VẬN HÀNH & ĐIỀU HƯỚNG (ROUTING)

Hệ thống hoạt động theo nguyên tắc **Zero Trust Network Access (ZTNA)**. Máy chủ tại nhà KHÔNG cần mở bất kỳ Port nào ra Router ngoài Internet, giúp bảo mật tuyệt đối.

**📌 Luồng đi của dữ liệu (Traffic Flow):**
1. Trình duyệt tải giao diện React tĩnh từ **Cloudflare Pages** (`https://toanrobert.online`).
2. Giao diện React gửi API request đến endpoint `https://api.toanrobert.online/api`.
3. Request đập vào trạm kiểm soát **Cloudflare Edge** ➜ Được mã hóa TLS ➜ Đi qua đường hầm **Cloudflare Tunnel**.
4. Đường hầm xuyên về máy Arch Linux, daemon `cloudflared` (chạy trong Docker) giải mã và đẩy traffic nội bộ vào container **Nginx (Port 80)**.
5. Nginx nhận diện virtual host `api.toanrobert.online`, xử lý static assets trực tiếp, hoặc chuyển tiếp xử lý động sang container **PHP-FPM (laravel_app:9000)** qua socket/FastCGI.
6. Laravel tương tác với container **MySQL** và **Redis** để lấy/ghi dữ liệu ➜ Phản hồi ngược lại theo đường cũ.

---

## PHẦN 3: CHI TIẾT CẤU HÌNH THỰC TẾ & THƯ MỤC DevOps

Để đảm bảo tính nhất quán, hệ thống được phân vùng thành hai khu vực tách biệt: thư mục mã nguồn (`~/educonnect/`) và thư mục hạ tầng (`~/production/`).

### 📂 1. THƯ MỤC HẠ TẦNG DEVOPS (~/production/)

**Cấu trúc thư mục thực tế:**
```text
~/production/
├── nginx/
│   └── conf.d/
│       └── api.toanrobert.online.conf
└── docker/
    ├── .env
    └── docker-compose.yml
```

#### **File 1.1: `~/production/docker/.env`** (Biến môi trường Production)
```env
# Mã token xác thực Cloudflare Tunnel
CLOUDFLARE_TUNNEL_TOKEN=eyJhIj...

# Cấu hình lõi Laravel
APP_NAME=EduConnect
APP_ENV=production
APP_DEBUG=false
APP_KEY=base64:ZxdKBXPKP0mKFruDqTsNCxCQjRlC...
APP_URL=https://api.toanrobert.online
NGINX_HOST=api.toanrobert.online

LOG_CHANNEL=stack
LOG_DEPRECATIONS_CHANNEL=null
LOG_LEVEL=error

# Database MySQL
DB_CONNECTION=mysql
DB_HOST=mysql
DB_PORT=3306
DB_DATABASE=educonnect_db
DB_USERNAME=admin
DB_PASSWORD=MatKhauAnToan2026!
DB_ROOT_PASSWORD=KietTacBaoMat2026!

# Redis Cache & Queue
REDIS_HOST=redis
REDIS_PASSWORD=AgriRedis2026!

# JWT Secret & Keys
JWT_SECRET=vaJwPcJArMyZauHZjDHmlbDDtCOkq8zH0hJg...
```

#### **File 1.2: `~/production/nginx/conf.d/api.toanrobert.online.conf`** (Nginx Virtual Host)
```nginx
server {
    listen 80;
    server_name api.toanrobert.online;

    root /var/www/html/public;
    index index.php index.html;

    client_max_body_size 100M;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ /\.(?!well-known) {
        deny all;
        return 404;
    }

    location ~ \.php$ {
        fastcgi_pass   app:9000;
        fastcgi_index  index.php;
        fastcgi_param  SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include        fastcgi_params;

        fastcgi_keep_conn on;

        # Chuyển tiếp IP thật từ Cloudflare Tunnel vào Laravel
        fastcgi_param  HTTP_X_REAL_IP        $http_x_real_ip;
        fastcgi_param  HTTP_X_FORWARDED_FOR  $proxy_add_x_forwarded_for;
        fastcgi_param  HTTP_X_FORWARDED_PROTO $http_x_forwarded_proto;
    }
}
```

#### **File 1.3: `~/production/docker/docker-compose.yml`** (Giao hương Container)
```yaml
version: '3.8'

services:
  # 1. PHP-FPM container (Laravel core)
  app:
    build:
      context: /home/robert/educonnect
      dockerfile: docker/php/Dockerfile
      target: production
    container_name: laravel_app
    restart: unless-stopped
    volumes:
      - storage_data:/var/www/html/storage/app
      - /home/robert/educonnect/storage/logs:/var/www/html/storage/logs
    env_file:
      - .env
    depends_on:
      mysql:
        condition: service_healthy
      redis:
        condition: service_healthy
    networks:
      - app-network

  # 2. Web server Nginx nội bộ
  nginx:
    image: nginx:alpine
    container_name: nginx_proxy
    restart: unless-stopped
    volumes:
      - /home/robert/educonnect:/var/www/html:ro
      - /home/robert/production/nginx/conf.d:/etc/nginx/conf.d:ro
    networks:
      - app-network
    depends_on:
      - app
    expose:
      - "80"

  # 3. Database MySQL
  mysql:
    image: mysql/mysql-server:8.0
    container_name: mysql_db
    restart: unless-stopped
    environment:
      MYSQL_ROOT_PASSWORD: ${DB_ROOT_PASSWORD}
      MYSQL_ROOT_HOST: "localhost"
      MYSQL_DATABASE: ${DB_DATABASE}
      MYSQL_USER: ${DB_USERNAME}
      MYSQL_PASSWORD: ${DB_PASSWORD}
    volumes:
      - ./data/mysql_data:/var/lib/mysql
    networks:
      - app-network
    healthcheck:
      test: ["CMD", "mysqladmin", "ping", "-h", "localhost", "-u", "${DB_USERNAME}", "-p${DB_PASSWORD}"]
      interval: 10s
      timeout: 5s
      retries: 5
      start_period: 60s

  # 4. Redis Cache & Queue
  redis:
    image: redis:7-alpine
    container_name: redis
    restart: unless-stopped
    command: redis-server --requirepass ${REDIS_PASSWORD} --maxmemory 256mb --maxmemory-policy allkeys-lru
    volumes:
      - ./data/redis_data:/data
    networks:
      - app-network
    healthcheck:
      test: ["CMD", "redis-cli", "-a", "${REDIS_PASSWORD}", "ping"]
      interval: 10s
      timeout: 5s
      retries: 3

  # 5. Queue Worker (Horizon)
  horizon:
    build:
      context: /home/robert/educonnect
      dockerfile: docker/php/Dockerfile
      target: production
    container_name: laravel_horizon
    restart: unless-stopped
    command: php /var/www/html/artisan horizon
    volumes:
      - storage_data:/var/www/html/storage/app
      - /home/robert/educonnect/storage/logs:/var/www/html/storage/logs
    env_file:
      - .env
    depends_on:
      mysql:
        condition: service_healthy
      redis:
        condition: service_healthy
    networks:
      - app-network

  # 6. Cron Scheduler
  scheduler:
    build:
      context: /home/robert/educonnect
      dockerfile: docker/php/Dockerfile
      target: production
    container_name: laravel_scheduler
    restart: unless-stopped
    command: >
      sh -c "while true; do
        php /var/www/html/artisan schedule:run --no-interaction;
        sleep 60;
      done"
    volumes:
      - storage_data:/var/www/html/storage/app
    env_file:
      - .env
    depends_on:
      mysql:
        condition: service_healthy
      redis:
        condition: service_healthy
    networks:
      - app-network

  # 7. Cloudflare Tunnel
  cloudflared:
    image: cloudflare/cloudflared:latest
    container_name: cloudflared
    restart: unless-stopped
    command: tunnel --no-autoupdate run --token ${CLOUDFLARE_TUNNEL_TOKEN}
    networks:
      - app-network
    depends_on:
      - nginx

volumes:
  storage_data:
  
networks:
  app-network:
    driver: bridge
```

---

## BƯỚC THIẾT LẬP KẾT NỐI TRÊN CLOUDFLARE WEB DASHBOARD
Trên trang quản trị Cloudflare Zero Trust ➜ Tunnels ➜ chọn Tunnel của bạn và thêm Public Hostname:
*   **Domain:** `api.toanrobert.online`
*   **Service Type:** `HTTP`
*   **URL:** `nginx:80` (Vì container `cloudflared` và `nginx_proxy` cùng thuộc `app-network`, `cloudflared` có thể gọi trực tiếp container Nginx bằng hostname qua cổng 80).

---

**QUY TRÌNH LÀM VIỆC HÀNG NGÀY (DEVELOPMENT WORKFLOW):**
- **Frontend**: Code và đẩy lên GitHub nhánh `main`, Cloudflare Pages sẽ tự phát hiện và biên dịch giao diện lên host.
- **Backend**: Sửa code PHP tại thư mục mã nguồn `~/educonnect/`.
- Khi cần cập nhật code lên production, di chuyển tới thư mục `~/educonnect/` và kích hoạt script deploy:
  ```bash
  ./deploy.sh
  ```
  Script sẽ tự động build assets, dựng image mới, migrate database và thực thi dọn dẹp/cache lại runtime configuration.