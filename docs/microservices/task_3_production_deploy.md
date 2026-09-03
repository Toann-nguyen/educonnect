# Task 3: Production Docker & Nginx Deployment Update

## 🎯 Mục tiêu
Cấu hình môi trường Production tại `/home/robert/production/` để sẵn sàng hỗ trợ các microservice (tạo database riêng, cập nhật `.env` và thiết lập Nginx routing).

---

## 📋 Hướng dẫn thực hiện chi tiết

### Step 3.1: Tạo Database Per-Service trên MySQL Container
Kết nối vào container MySQL Production hoặc dùng MySQL client trên host để tạo database:

```bash
docker exec -it mysql_db mysql -u root -p -e "CREATE DATABASE IF NOT EXISTS identity_db; CREATE DATABASE IF NOT EXISTS school_db; GRANT ALL PRIVILEGES ON identity_db.* TO 'educonnect'@'%'; GRANT ALL PRIVILEGES ON school_db.* TO 'educonnect'@'%'; FLUSH PRIVILEGES;"
```

---

### Step 3.2: Cập nhật Biến Môi Trường Production
Chỉnh sửa file `/home/robert/production/docker/.env`:

Thêm/cập nhật các biến:
```env
# Database Identity
DB_IDENTITY_HOST=mysql
DB_IDENTITY_PORT=3306
DB_IDENTITY_DATABASE=identity_db
DB_IDENTITY_USERNAME=educonnect
DB_IDENTITY_PASSWORD=your_secure_password

# Database School
DB_SCHOOL_HOST=mysql
DB_SCHOOL_PORT=3306
DB_SCHOOL_DATABASE=school_db
DB_SCHOOL_USERNAME=educonnect
DB_SCHOOL_PASSWORD=your_secure_password
```

Sau đó restart container app:
```bash
cd /home/robert/production/docker
docker compose restart app horizon
```

---

### Step 3.3: Cập nhật Nginx Gateway Proxy (Sẵn sàng cho Go Services)
File cấu hình: `/home/robert/production/nginx/conf.d/api.toanrobert.online.conf`

Khi chuẩn bị tách sang Go service (Finance / Notify), bổ sung các block proxy sau vào server Nginx:

```nginx
# Finance Service (Go)
location /api/finance/ {
    proxy_pass http://finance_app:8080/;
    proxy_set_header Host $host;
    proxy_set_header X-Real-IP $remote_addr;
    proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
    proxy_set_header X-Forwarded-Proto $scheme;
}

# Notify Service (Go)
location /api/notify/ {
    proxy_pass http://notify_app:8081/;
    proxy_set_header Host $host;
    proxy_set_header X-Real-IP $remote_addr;
    proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
    proxy_set_header X-Forwarded-Proto $scheme;
}
```

Reload lại Nginx:
```bash
docker exec -it nginx_proxy nginx -s reload
```

---

## ✅ Kiểm tra hoàn tất (Verification)
- Chạy `docker exec -it laravel_app php artisan migrate --database=identity --path=database/migrations/identity` thành công.
- API `/api/auth/login` vẫn hoạt động ổn định trên Production.
