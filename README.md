<p align="center">
<a href="https://laravel.com" target="_blank"><img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="400" style="vertical-align: middle;" alt="Laravel Logo"></a>
<a href="https://redis.io" target="_blank"><img src="https://cdn.jsdelivr.net/gh/devicons/devicon/icons/redis/redis-original-wordmark.svg" width="150" style="vertical-align: middle;" alt="Redis Logo"></a>
<a href="https://nginx.org" target="_blank"><img src="https://cdn.jsdelivr.net/gh/devicons/devicon/icons/nginx/nginx-original.svg" width="120" style="vertical-align: middle;" alt="Nginx Logo"></a>
<a href="https://www.docker.com" target="_blank"><img src="https://cdn.jsdelivr.net/gh/devicons/devicon/icons/docker/docker-original-wordmark.svg" width="150" style="vertical-align: middle;" alt="Docker Logo"></a>
<a href="https://phpunit.de" target="_blank"><img src="https://phpunit.de/img/phpunit.svg" width="150" style="vertical-align: middle; margin-left: 20px;" alt="PHPUnit Logo"></a>
</p>

<p align="center">
<a href="https://github.com/laravel/framework/actions"><img src="https://github.com/laravel/framework/workflows/tests/badge.svg" alt="Build Status"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/dt/laravel/framework" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/v/laravel/framework" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/l/laravel/framework" alt="License"></a>
</p>

# 🎓 EduConnect

> Hệ thống quản lý giáo dục kết nối học sinh, phụ huynh và nhà trường.

---

## 🛠️ Tech Stack

### 🐘 Backend

| | Công nghệ | Mô tả |
|---|-----------|-------|
| ⚡ | **PHP 8.1** + **Laravel 10** | Framework chính |
| 🗄️ | **MySQL** | Database quan hệ |
| 🟥 | **Redis** | Cache & Queue |
| 📊 | **Laravel Horizon** | Queue monitoring dashboard |
| ❤️‍🔥 | **Laravel Pulse** | Server & application monitoring |

### 🔐 Authentication & Security

| | Package | Mục đích |
|---|---------|----------|
| 🔑 | JWT Auth | Xác thực API bằng JSON Web Token |
| 🛡️ | Laravel Sanctum | API token + SPA authentication |
| 🌐 | Laravel Socialite | Đăng nhập qua Google, GitHub, Facebook (OAuth) |
| 👥 | Spatie Laravel Permission | Role-Based Access Control (RBAC) |
| 🔢 | Google2FA | Xác thực hai yếu tố (TOTP / 2FA) |

### 🟢 Frontend

| | Công nghệ | Mô tả |
|---|-----------|-------|
| 💚 | **Vue 3** | UI framework |
| 🔗 | **Inertia.js** | Kết nối Laravel + Vue 3 (SPA không cần API riêng) |
| 🎨 | **Tailwind CSS 3** | Utility-first CSS framework |
| ⚡ | **Vite** | Build tool |
| 📡 | **Axios** | HTTP client |

### 📱 Communication

| | Package | Mục đích |
|---|---------|----------|
| ✉️ | Twilio SDK | Gửi SMS |
| 📞 | libphonenumber-for-php | Validate & format số điện thoại |
| 🌍 | Guzzle | HTTP client |

### 📖 API Documentation

| | Package | Mục đích |
|---|---------|----------|
| 📝 | Scribe | Tạo tài liệu API tự động |
| 🦢 | L5-Swagger | Giao diện Swagger/OpenAPI |

### 📦 Infrastructure

| | Thành phần | Công nghệ |
|---|------------|-----------|
| 🗄️ | **Database** | MySQL 8.0+ |
| 🟥 | **Cache** | Redis 7 |
| 🟥 | **Queue** | Redis (Laravel Horizon) |
| 📧 | **Mail** | SMTP |
| 🐳 | **Container** | Docker + Docker Compose |

---

## ✨ Tính năng chính

- ✅ Đăng nhập / đăng ký (JWT + Sanctum)
- ✅ Đăng nhập qua mạng xã hội (Google, GitHub, Facebook)
- ✅ Xác thực hai yếu tố (2FA)
- ✅ Phân quyền người dùng (RBAC)
- ✅ Gửi SMS qua Twilio
- ✅ Queue xử lý bất đồng bộ (Horizon)
- ✅ API Documentation (Swagger)
- ✅ Giao diện SPA với Inertia.js + Vue 3

---

## 🚀 Cài đặt nhanh

```bash
# 1. Clone repo
git clone <repo-url>
cd educonnect

# 2. Cài đặt PHP dependencies
composer install

# 3. Cài đặt JS dependencies
npm install

# 4. Sao chép env
cp .env.example .env
php artisan key:generate

# 5. Chạy migration
php artisan migrate

# 6. Build frontend
npm run build

# 7. Khởi chạy
php artisan serve
```

> ⚡ Hoặc dùng Docker: `docker-compose up -d`

---

## 📁 Cấu trúc thư mục

```
app/           → Laravel application (Controllers, Models, ...)
config/        → Cấu hình
database/      → Migrations & Seeders
resources/js/  → Vue 3 components (Inertia Pages)
routes/        → Web & API routes
docker/        → Docker config
```

---

## 📄 Tài liệu tham khảo

- [TECH-STACK.md](TECH-STACK.md) — Chi tiết phiên bản packages
- [README-API.md](README-API.md) — Hướng dẫn API
- [README-setup.md](README-setup.md) — Hướng dẫn cài đặt chi tiết
