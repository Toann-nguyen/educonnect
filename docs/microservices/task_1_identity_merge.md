# Task 1: Merge Identity Service & Refactor Namespaces

## 🎯 Mục tiêu
Merge toàn bộ source code từ dự án `~/educonnect-identity/` vào `~/educonnect/app/Domains/Identity/`, refactor namespace và xóa các file legacy model trùng lặp.

---

## 📋 Hướng dẫn thực hiện chi tiết

### Step 1.1: Copy source code từ dự án Identity sang Monolith
Chép toàn bộ các file nghiệp vụ trong `~/educonnect-identity/app/` sang `~/educonnect/app/Domains/Identity/`:
- `app/Models/` -> `app/Domains/Identity/Models/`
- `app/Http/Controllers/` -> `app/Domains/Identity/Http/Controllers/`
- `app/Http/Requests/` -> `app/Domains/Identity/Http/Requests/`
- `app/Http/Resources/` -> `app/Domains/Identity/Http/Resources/`
- `app/Services/` -> `app/Domains/Identity/Services/`
- `app/Repositories/` -> `app/Domains/Identity/Repositories/`
- `app/Jobs/`, `app/Events/`, `app/Exceptions/`, `app/Mail/` -> `app/Domains/Identity/...`

Chép file route:
- `~/educonnect-identity/routes/api.php` -> `~/educonnect/routes/identity.php`

---

### Step 1.2: Refactor Namespace trong `app/Domains/Identity/`
Thay đổi namespace cho tất cả các file vừa copy:
- `namespace App\Models;` -> `namespace App\Domains\Identity\Models;`
- `namespace App\Http\Controllers...;` -> `namespace App\Domains\Identity\Http\Controllers...;`
- `namespace App\Services...;` -> `namespace App\Domains\Identity\Services...;`
- `namespace App\Repositories...;` -> `namespace App\Domains\Identity\Repositories...;`

Đồng thời sửa tất cả các câu lệnh `use App\...` trong `app/Domains/Identity/` tương ứng.

---

### Step 1.3: Cập nhật Config & Route Registration
1. File `config/auth.php`:
```php
'providers' => [
    'users' => [
        'driver' => 'eloquent',
        'model' => App\Domains\Identity\Models\User::class,
    ],
],
```
2. File `app/Providers/RouteServiceProvider.php` (hoặc `routes/web.php`/`routes/api.php`):
Đăng ký route `routes/identity.php` với prefix `api` hoặc load tự động.

---

### Step 1.4: Xóa Model Legacy & Refactor Tham chiếu Toàn dự án
1. Xóa các model legacy tại `~/educonnect/app/Models/`:
   - `User.php`, `Role.php`, `Permission.php`, `Profile.php`, `BackupCode.php`, `UserSession.php`, `RefreshToken.php`, `PasswordResetToken.php`, `AuditLog.php`, `EmailVerification.php`.

2. Tìm kiếm và thay thế (Find & Replace) trên toàn bộ thư mục `app/` và `routes/`:
   - Replace: `use App\Models\User;` -> `use App\Domains\Identity\Models\User;`
   - Replace: `use App\Models\Role;` -> `use App\Domains\Identity\Models\Role;`
   - Replace: `use App\Models\Permission;` -> `use App\Domains\Identity\Models\Permission;`

---

## ✅ Kiểm tra hoàn tất (Verification)
- Chạy `composer dump-autoload`.
- Chạy `php artisan route:list` không báo lỗi class not found.
- Chạy unit test auth (nếu có): `php artisan test`.
