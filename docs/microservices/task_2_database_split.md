# Task 2: Database & Migration Per-Service Setup

## 🎯 Mục tiêu
Cấu hình 2 kết nối database riêng biệt (`identity` và `school`), gán thuộc tính `$connection` trong Models và tổ chức thư mục migration độc lập cho từng service.

---

## 📋 Hướng dẫn thực hiện chi tiết

### Step 2.1: Cấu hình `config/database.php`
Thêm 2 connections `identity` và `school` trong mảng `connections`:

```php
'identity' => [
    'driver' => 'mysql',
    'url' => env('DATABASE_IDENTITY_URL'),
    'host' => env('DB_IDENTITY_HOST', '127.0.0.1'),
    'port' => env('DB_IDENTITY_PORT', '3306'),
    'database' => env('DB_IDENTITY_DATABASE', 'identity_db'),
    'username' => env('DB_IDENTITY_USERNAME', 'forge'),
    'password' => env('DB_IDENTITY_PASSWORD', ''),
    'charset' => 'utf8mb4',
    'collation' => 'utf8mb4_unicode_ci',
    'prefix' => '',
    'strict' => true,
    'engine' => null,
],

'school' => [
    'driver' => 'mysql',
    'url' => env('DATABASE_SCHOOL_URL'),
    'host' => env('DB_SCHOOL_HOST', '127.0.0.1'),
    'port' => env('DB_SCHOOL_PORT', '3306'),
    'database' => env('DB_SCHOOL_DATABASE', 'school_db'),
    'username' => env('DB_SCHOOL_USERNAME', 'forge'),
    'password' => env('DB_SCHOOL_PASSWORD', ''),
    'charset' => 'utf8mb4',
    'collation' => 'utf8mb4_unicode_ci',
    'prefix' => '',
    'strict' => true,
    'engine' => null,
],
```

---

### Step 2.2: Khai báo `$connection` trong Models

1. Mở tất cả các file trong `app/Domains/Identity/Models/` và thêm:
```php
protected $connection = 'identity';
```
2. Mở các file Model thuộc School domain (hiện đang nằm ở `app/Models/` hoặc `app/Domains/School/Models/`) và thêm:
```php
protected $connection = 'school';
```

---

### Step 2.3: Tổ chức Thư mục Migrations & Chạy Migration
1. Tạo 2 thư mục:
   - `database/migrations/identity/`
   - `database/migrations/school/`

2. Di chuyển các migration file liên quan tới auth (users, roles, permissions, sessions, tokens...) vào `database/migrations/identity/`.
3. Di chuyển các migration file liên quan tới trường học (students, classes, subjects, grades...) vào `database/migrations/school/`.

4. Lệnh chạy Migration per-service:
```bash
# Migrate Identity Database
php artisan migrate --database=identity --path=database/migrations/identity

# Migrate School Database
php artisan migrate --database=school --path=database/migrations/school
```

---

### Step 2.4: Reset Spatie RBAC Cache
Sau khi đã cấu hình connection & namespace mới cho Identity/User/Role, chạy lệnh:
```bash
php artisan permission:cache-reset
```

---

## ✅ Kiểm tra hoàn tất (Verification)
- Kiểm tra bảng `migrations` trong database `identity_db` và `school_db` để đảm bảo migration chạy riêng biệt không đè lên nhau.
