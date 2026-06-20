# 🛠️ EduConnect - Tech Stack

> Cập nhật: 2026-06-16

## Môi trường

| Môi trường | Phiên bản |
|---|---|
| PHP | ^8.1 |
| Node.js | 18+ |
| NPM | 9+ |
| Composer | 2+ |

---

## Backend - Laravel

### Core

| Package | Version | Mục đích |
|---|---|---|
| `laravel/framework` | ^10.10 | Framework chính |
| `laravel/tinker` | ^2.8 | Interactive shell |

### Authentication & Security

| Package | Version | Mục đích |
|---|---|---|
| `php-open-source-saver/jwt-auth` | ^2.3 | JWT Authentication (thay thế `tymon/jwt-auth` đã deprecated) |
| `laravel/sanctum` | ^3.3 | API token authentication, SPA authentication |
| `spatie/laravel-permission` | ^6.25 | Role-Based Access Control (RBAC) |
| `laravel/socialite` | ^5.27 | OAuth2 login (Google, GitHub, Facebook...) |
| `pragmarx/google2fa-laravel` | ^3.0 | TOTP / 2FA xác thực hai yếu tố |

### QR Code

| Package | Version | Mục đích |
|---|---|---|
| `bacon/bacon-qr-code` | ^2.0 | QR Code generation engine |
| `simplesoftwareio/simple-qrcode` | ^4.2 | Laravel wrapper cho QR Code |

### API & Documentation

| Package | Version | Mục đích |
|---|---|---|
| `knuckleswtf/scribe` | ^5.10 | API documentation generator |
| `darkaonline/l5-swagger` | ^8.6 | Swagger/OpenAPI documentation |

### Database & Queue

| Package | Version | Mục đích |
|---|---|---|
| — | — | **MySQL** (database), cấu hình tại `DB_CONNECTION=mysql` |
| `laravel/horizon` | ^5.46 | Queue monitoring dashboard (Redis) |
| `laravel/pulse` | ^1.7 | Server & application monitoring |

### Notification & Communication

| Package | Version | Mục đích |
|---|---|---|
| `twilio/sdk` | ^8.11 | SMS gateway (Twilio) |
| `giggsey/libphonenumber-for-php` | ^9.0 | Phone number validation & formatting |
| `guzzlehttp/guzzle` | ^7.2 | HTTP client (mail, API calls) |

### Development Tools

| Package | Version | Mục đích |
|---|---|---|
| `laravel/breeze` | ^1.29 | Authentication scaffolding (dev) |
| `barryvdh/laravel-ide-helper` | ^3.1 | IDE auto-completion (dev) |
| `laravel/sail` | ^1.45 | Docker development environment (dev) |
| `knuckleswtf/scribe` | ^5.10 | API docs (dev) |
| `laravel/pint` | ^1.0 | PHP code style fixer (dev) |
| `phpunit/phpunit` | ^10.1 | Testing framework (dev) |
| `mockery/mockery` | ^1.4.4 | Mocking framework (dev) |
| `fakerphp/faker` | ^1.9.1 | Fake data generator (dev) |
| `nunomaduro/collision` | ^7.0 | Error handling (dev) |
| `spatie/laravel-ignition` | ^2.0 | Error page (dev) |

---

## Frontend

| Package | Version | Mục đích |
|---|---|---|
| `vue` | ^3.4.0 | UI framework |
| `@inertiajs/vue3` | ^1.0.0 | Inertia.js adapter cho Vue 3 (SPA không cần API route riêng) |
| `tailwindcss` | ^3.2.1 | Utility-first CSS framework |
| `@tailwindcss/forms` | ^0.5.3 | Form reset styles cho Tailwind |
| `vite` | ^8.0.12 | Build tool |
| `@vitejs/plugin-vue` | ^5.0.0 | Vite plugin cho Vue 3 |
| `laravel-vite-plugin` | ^1.0.0 | Laravel integration với Vite |
| `axios` | ^1.6.4 | HTTP client |
| `lodash` | ^4.18.1 | Utility library |
| `postcss` | ^8.4.31 | CSS processor |
| `autoprefixer` | ^10.4.12 | CSS vendor prefixes |
| `ziggy` | ^2.0 | Laravel route helper cho JS (qua `tightenco/ziggy`) |

---

## Infrastructure

| Thành phần | Công nghệ | Ghi chú |
|---|---|---|
| **Database** | MySQL 8.0+ | DB_CONNECTION=mysql |
| **Cache** | Redis 7 | Cấu hình sẵn trong `config/database.php` |
| **Queue Driver** | Redis (sync hiện tại) | QUEUE_CONNECTION=sync (`.env`), chuyển `redis` khi deploy |
| **Queue Dashboard** | Laravel Horizon | `laravel/horizon` |
| **Mail Driver** | SMTP | MAIL_MAILER=smtp (`.env`), có thể đổi SES/Mailgun khi deploy |
| **Filesystem** | Local | FILESYSTEM_DISK=local |

---

## So sánh với thiết kế ban đầu

> Dựa trên ảnh kiến trúc tham khảo, có một số điểm khác biệt:

| Mục | Thiết kế tham khảo | Thực tế | Ghi chú |
|---|---|---|---|
| **JWT Package** | `tymon/jwt-auth` ^2.0 | `php-open-source-saver/jwt-auth` ^2.3 | Fork chính thức, tymon đã archived |
| **Database** | PostgreSQL 15+ | MySQL 8.0+ | Có thể migrate sau nếu cần |
| **Queue** | Redis driver | Redis (hiện sync) | Đổi QUEUE_CONNECTION=redis khi deploy |
| **Mail** | AWS SES / Mailgun | SMTP | Cấu hình lại khi deploy |
| **NestJS Stack** | NestJS 10, Prisma, BullMQ... | **Không có** | Chỉ dùng Laravel full-stack + Inertia.js |
| **Redis Client** | predis | Native PhpRedis | predis không cần khai báo riêng |

---

## Ghi chú

- **JWT**: Dùng `php-open-source-saver/jwt-auth` thay vì `tymon/jwt-auth` vì package cũ đã ngừng bảo trì.
- **Database**: Dùng MySQL (có thể migrate lên PostgreSQL nếu cần).
- **Queue**: Chạy `php artisan queue:work` hoặc Horizon (`php artisan horizon`) khi cần xử lý bất đồng bộ.
- **Frontend**: SPA qua Inertia.js + Vue 3, không cần xây dựng API REST riêng cho frontend.
