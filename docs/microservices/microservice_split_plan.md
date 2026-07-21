# Kế hoạch chi tiết tách Monolith thành Microservices (Identity & Database Split)

## 1. Goal Description
Tách dự án Monolith `educonnect` thành cấu trúc modular microservices trong cùng một repo (monorepo/modular monolith) theo kế hoạch `MICROSERVICE_TASKS.md`. Trọng tâm của giai đoạn này là thực hiện **Merge Identity service (đang ở folder riêng) vào `app/Domains/Identity`**, refactor toàn bộ namespace liên quan và cấu hình database riêng biệt cho từng service (per-service DB). Do phần frontend sẽ được tách riêng và không cần dọn dẹp trong đợt này, toàn bộ code frontend hiện có sẽ được giữ nguyên.

---

## 2. User Review Required
> [!IMPORTANT]
> **Refactor Namespace diện rộng:**
> Khi chuyển các Model liên quan đến Auth sang `App\Domains\Identity\Models`, toàn bộ các file model, controller, service khác trong monolith đang import `App\Models\User` sẽ phải được refactor để import `App\Domains\Identity\Models\User`.
>
> **Tách Database:**
> Cấu hình kết nối database trong `config/database.php` sẽ tách làm 2 connection riêng biệt: `identity` (cho `identity_db`) và `school` (cho `school_db`). Các model sẽ tự động trỏ sang connection tương ứng qua thuộc tính `$connection`.

---

## 3. Open Questions
> [!NOTE]
> 1. **Dữ liệu Seeders:** Việc chạy seeders cho phân hệ Identity có cần tách riêng biệt hay không? Hiện tại Laravel đang dùng một file `DatabaseSeeder.php` chung.
> 2. **Go Services (Finance/Notify):** Go service sẽ được đặt tại đâu trong monorepo? Đề xuất đặt tại thư mục riêng biệt ở root: `services/finance/` và `services/notify/` để dễ quản lý Go module, tránh nhầm lẫn với cấu trúc PHP.

---

## 4. Proposed Changes

### Component 1: Merge Identity Service (`app/Domains/Identity`)
Copy code từ `/home/robert/educonnect-identity/app/` sang `/home/robert/educonnect/app/Domains/Identity/` và refactor namespace.

---

#### [NEW] Cấu trúc thư mục app/Domains/Identity/
Tạo các folder con chứa code của Identity domain:
- `app/Domains/Identity/Models/`
- `app/Domains/Identity/Http/`
- `app/Domains/Identity/Services/`
- `app/Domains/Identity/Repositories/`
- `app/Domains/Identity/Events/`
- `app/Domains/Identity/Jobs/`
- `app/Domains/Identity/Exceptions/`
- `app/Domains/Identity/Mail/`

#### [NEW] File copy từ identity sang domains
Sao chép các file nghiệp vụ auth đã hoàn thành từ `~/educonnect-identity/app/` vào đúng thư mục cấu trúc tương ứng trong `~/educonnect/app/Domains/Identity/`.

#### [MODIFY] Refactor Namespaces của Identity
Mỗi file được copy vào `app/Domains/Identity/` sẽ được cập nhật namespace từ `App\...` thành `App\Domains\Identity\...`.
Ví dụ:
```php
// app/Domains/Identity/Models/User.php
namespace App\Domains\Identity\Models;
// ...
```
Đồng thời, cập nhật các lệnh `use` bên trong các file này để tham chiếu đúng các class nội bộ mới.

#### [NEW] routes/identity.php
Định nghĩa route riêng cho phân hệ Identity (được chuyển từ `routes/api.php` của project identity).
```php
<?php

use App\Domains\Identity\Http\Controllers\Auth\AuthController;
use App\Domains\Identity\Http\Controllers\Auth\EmailController;
use App\Domains\Identity\Http\Controllers\Auth\PasswordController;
use App\Domains\Identity\Http\Controllers\Auth\TwoFactorController;
use App\Domains\Identity\Http\Controllers\Auth\SessionController;
use App\Domains\Identity\Http\Controllers\RoleController;
use App\Domains\Identity\Http\Controllers\PermissionController;
use App\Domains\Identity\Http\Controllers\UserRoleController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
    Route::post('/reset-password', [AuthController::class, 'resetPassword']);
    Route::post('/email/verify', [AuthController::class, 'verifyEmail']);
    Route::post('/refresh', [AuthController::class, 'refresh']);
});

Route::middleware('auth.jwt')->prefix('auth')->group(function () {
    Route::get('me', [AuthController::class, 'user']);
    Route::post('logout', [AuthController::class, 'logout']);
    Route::post('logout/all', [AuthController::class, 'logoutAll']);
    Route::post('email/verify/send', [EmailController::class, 'send']);
    Route::put('password/change', [PasswordController::class, 'change']);
    Route::apiResource('sessions', SessionController::class)->only(['index', 'destroy']);
    Route::prefix('2fa')->group(function () {
        Route::post('totp/setup', [TwoFactorController::class, 'setup']);
        Route::post('totp/enable', [TwoFactorController::class, 'enable']);
        Route::post('totp/disable', [TwoFactorController::class, 'disable']);
        Route::get('backup-codes', [TwoFactorController::class, 'backupCodes']);
        Route::post('backup-codes/regenerate', [TwoFactorController::class, 'regenerateBackupCodes']);
    });
});

Route::middleware(['auth.jwt', 'role:admin'])->group(function () {
    Route::get('admin/roles', [RoleController::class, 'index']);
    Route::post('admin/roles', [RoleController::class, 'store']);
    Route::get('admin/roles/{id}', [RoleController::class, 'show']);
    Route::put('admin/roles/{id}', [RoleController::class, 'update']);
    Route::delete('admin/roles/{id}', [RoleController::class, 'destroy']);
    Route::get('permissions', [PermissionController::class, 'index']);
    Route::get('permissions/{id}', [PermissionController::class, 'show']);
    Route::post('admin/permissions', [PermissionController::class, 'store']);
    Route::put('admin/permissions/{id}', [PermissionController::class, 'update']);
    Route::delete('admin/permissions/{id}', [PermissionController::class, 'destroy']);
    Route::get('admin/roles/{id}/permissions', [RoleController::class, 'getRolePermissions']);
    Route::post('admin/roles/{id}/permissions', [RoleController::class, 'assignPermissions']);
    Route::delete('admin/roles/{role}/permissions/{permission}', [RoleController::class, 'removePermission']);
    Route::get('admin/users/{userId}/roles', [UserRoleController::class, 'getUserRoles']);
    Route::get('admin/users/{userId}/permissions', [UserRoleController::class, 'getUserPermissions']);
    Route::post('admin/users/{userId}/roles', [UserRoleController::class, 'assign']);
    Route::delete('admin/users/{userId}/roles/{role}', [UserRoleController::class, 'revoke']);
});
```

#### [MODIFY] config/auth.php
Thay đổi User model provider trỏ sang class mới:
```diff
     'providers' => [
         'users' => [
             'driver' => 'eloquent',
-            'model' => App\Models\User::class,
+            'model' => App\Domains\Identity\Models\User::class,
         ],
     ],
```

#### [DELETE] Các file model legacy của Identity trong `app/Models/`
Xóa các file cũ tránh xung đột:
- `User.php`
- `Role.php`
- `Permission.php`
- `Profile.php`
- `BackupCode.php`
- `UserSession.php`
- `RefreshToken.php`
- `PasswordResetToken.php`
- `AuditLog.php`
- `EmailVerification.php`

#### [MODIFY] Cập nhật imports trong phần còn lại của Monolith
Dùng lệnh tìm kiếm và thay thế (refactor) tất cả file trong `app/` và `routes/` (ngoài Identity domain) để cập nhật import:
- `use App\Models\User;` -> `use App\Domains\Identity\Models\User;`
- `use App\Models\Role;` -> `use App\Domains\Identity\Models\Role;`
- (Tương tự cho các model rác khác nếu có tham chiếu chéo).

---

### Component 2: Cấu hình Tách Database & Migrations

---

#### [MODIFY] config/database.php
Thêm kết nối `identity` và `school` trong mục `connections`:
```php
        'identity' => [
            'driver' => 'mysql',
            'url' => env('DATABASE_IDENTITY_URL'),
            'host' => env('DB_IDENTITY_HOST', '127.0.0.1'),
            'port' => env('DB_IDENTITY_PORT', '3306'),
            'database' => env('DB_IDENTITY_DATABASE', 'identity_db'),
            'username' => env('DB_IDENTITY_USERNAME', 'forge'),
            'password' => env('DB_IDENTITY_PASSWORD', ''),
            'unix_socket' => env('DB_SOCKET', ''),
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
            'prefix_indexes' => true,
            'strict' => true,
            'engine' => null,
            'options' => extension_loaded('pdo_mysql') ? array_filter([
                PDO::MYSQL_ATTR_SSL_CA => env('MYSQL_ATTR_SSL_CA'),
            ]) : [],
        ],

        'school' => [
            'driver' => 'mysql',
            'url' => env('DATABASE_SCHOOL_URL'),
            'host' => env('DB_SCHOOL_HOST', '127.0.0.1'),
            'port' => env('DB_SCHOOL_PORT', '3306'),
            'database' => env('DB_SCHOOL_DATABASE', 'school_db'),
            'username' => env('DB_SCHOOL_USERNAME', 'forge'),
            'password' => env('DB_SCHOOL_PASSWORD', ''),
            'unix_socket' => env('DB_SOCKET', ''),
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
            'prefix_indexes' => true,
            'strict' => true,
            'engine' => null,
            'options' => extension_loaded('pdo_mysql') ? array_filter([
                PDO::MYSQL_ATTR_SSL_CA => env('MYSQL_ATTR_SSL_CA'),
            ]) : [],
        ],
```

#### [MODIFY] Khai báo `$connection` trong Model
- Tất cả Model trong `App\Domains\Identity\Models` sẽ khai báo `protected $connection = 'identity';`.
- Tất cả Model thuộc phân hệ School (Student, SchoolClass, Subject, Grade...) sẽ khai báo `protected $connection = 'school';`.

#### [NEW] Tách thư mục Migrations
Tạo các thư mục migrations riêng biệt:
- `database/migrations/identity/`
- `database/migrations/school/`
Di chuyển các file migration tương ứng vào từng thư mục này. 

---

## 5. Verification Plan

### Automated Tests
1. Chạy composer dump-autoload:
   ```bash
   composer dump-autoload
   ```
2. Chạy test suite để đảm bảo việc refactor namespace không gây ra lỗi logic biên dịch:
   ```bash
   php artisan test
   ```

### Manual Verification
1. Liệt kê danh sách route:
   ```bash
   php artisan route:list
   ```
   Đảm bảo không còn routes web/Inertia/Breeze cũ và các endpoint của `/api/auth/*` trỏ đúng vào `App\Domains\Identity\Http\Controllers\...`.
2. Kiểm tra kết nối tới các DB bằng cách chạy migration cho từng service:
   ```bash
   php artisan migrate --database=identity --path=database/migrations/identity
   php artisan migrate --database=school --path=database/migrations/school
   ```

---

## 6. Common Pitfalls
- **Lỗi Autoload:** Quên chạy `composer dump-autoload` khiến Laravel không nhận diện được namespace mới `App\Domains\Identity`.
- **Thiếu DB Connection:** Nếu không cấu hình biến môi trường `DB_IDENTITY_DATABASE` và `DB_SCHOOL_DATABASE` trong file `.env`, migrations và test sẽ thất bại.
- **Spatie RBAC Cache:** Cache của Spatie lưu trữ model class name. Khi đổi namespace của `User`, ta cần clear cache rbac bằng `php artisan permission:cache-reset` để tránh lỗi ClassNotFound.
