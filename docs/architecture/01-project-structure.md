# Cấu trúc dự án EduConnect

## 1. Tổng quan kiến trúc

EduConnect là ứng dụng **Laravel 10 full-stack** (PHP 8.1+) phục vụ quản lý giáo dục, với frontend là **Vue 3 + Inertia.js SPA**. Ứng dụng sử dụng:
- **JWT authentication** (`php-open-source-saver/jwt-auth`)
- **Spatie RBAC** (quản lý vai trò và quyền hạn)
- **Redis** cho caching và queue
- **MySQL** với **3 kết nối database riêng biệt** (`identity`, `school`, `finance`)

## 2. Cấu trúc thư mục theo Domain

Dự án được tổ chức theo mô hình **Domain-Driven Design** với 3 domain chính:

```
app/
├── Domains/
│   ├── Identity/          # Domain xác thực & quản lý người dùng
│   │   ├── Models/
│   │   ├── Http/
│   │   │   ├── Controllers/
│   │   │   ├── Requests/
│   │   │   └── Resources/
│   │   ├── Repositories/
│   │   │   ├── Contracts/
│   │   │   └── Eloquent/
│   │   ├── Services/
│   │   │   ├── Interface/
│   │   │   └── ...
│   │   ├── Policies/
│   │   ├── Middleware/
│   │   ├── Jobs/
│   │   ├── Mail/
│   │   ├── Events/
│   │   └── Exceptions/
│   │
│   ├── School/            # Domain quản lý trường học
│   │   ├── Models/
│   │   ├── Http/
│   │   │   ├── Controllers/
│   │   │   ├── Requests/
│   │   │   └── Resources/
│   │   ├── Repositories/
│   │   │   ├── Contracts/
│   │   │   └── Eloquent/
│   │   ├── Services/
│   │   │   ├── Interface/
│   │   │   └── ...
│   │   ├── Policies/
│   │   ├── Jobs/
│   │   └── Exceptions/
│   │
│   └── Finance/           # Domain quản lý tài chính
│       ├── Models/
│       ├── Repositories/
│       │   ├── Contracts/
│       │   └── Eloquent/
│       ├── Services/
│       │   ├── Interface/
│       │   └── ...
│       └── Exceptions/
│
├── Enums/                 # Enum toàn cục
├── Helpers/               # Helper functions
├── Traits/                # Traits dùng chung
├── Http/
│   ├── Middleware/         # Legacy middleware (một số đã chuyển vào domain)
│   └── Controllers/
│       └── Api/
│           └── TestController.php  # Controller legacy còn sót lại
├── Services/              # ⚠️ THƯ MỤC KHÔNG TỒN TẠI (được tham chiếu trong AppServiceProvider)
├── Repositories/          # ⚠️ THƯ MỤC KHÔNG TỒN TẠI (được tham chiếu trong AppServiceProvider)
└── Providers/
    ├── AppServiceProvider.php      # Central DI wiring
    └── RouteServiceProvider.php    # Đăng ký routes
```

## 3. Phân chia module theo chức năng

| Domain | Mục đích | Trạng thái |
|--------|----------|------------|
| **Identity** | Xác thực, người dùng, vai trò, quyền hạn, hồ sơ, phiên làm việc, 2FA, audit logs | ✅ Hoạt động đầy đủ |
| **School** | Học sinh, lớp học, điểm số, thời khóa biểu, điểm danh, kỷ luật, thư viện, sự kiện | ⚠️ Một số controller chưa hoàn thiện |
| **Finance** | Hóa đơn, thanh toán, loại phí | ❌ Chưa có HTTP layer (controllers/routes) |

## 4. Cấu trúc routes

Routes được chia thành 4 file, tất cả được load bởi `RouteServiceProvider`:

| File | Mục đích |
|------|----------|
| `routes/identity.php` | Auth, roles, permissions, user management (JWT + role middleware) |
| `routes/school.php` | School CRUD, schedules, grades, disciplines, conduct scores |
| `routes/api.php` | Chỉ có health check (API cũ đã được chuyển vào `.bak`) |
| `routes/web.php` | Root endpoint trả về JSON |

⚠️ **Lưu ý**: File `routes/api.php.bak` chứa ~200+ dòng routes cũ (QR codes, OAuth, legacy endpoints) chưa được di chuyển hoàn toàn vào cấu trúc domain mới.

## 5. Database Connections

Ba kết nối database được định nghĩa trong `config/database.php`:

| Connection | Mục đích | Domain sử dụng |
|------------|----------|----------------|
| `mysql` (default) | Kết nối mặc định | Identity |
| `identity` | Database người dùng | Identity |
| `school` | Database trường học | School |

⚠️ **Vấn đề nghiêm trọng**: Connection `finance` **KHÔNG ĐƯỢC ĐỊNH NGHĨA** trong `config/database.php`, nhưng tất cả Finance models (`Invoice`, `Payment`, `FeeType`, `InvoiceItem`) đều khai báo `protected $connection = 'finance'`. Điều này sẽ gây lỗi `InvalidArgumentException` khi runtime.

## 6. Sơ đồ phụ thuộc giữa các Domain

```
┌─────────────────────────────────────────────────────────────────┐
│                        Identity Domain                          │
│  (Auth, Users, Roles, Permissions, 2FA, Audit Logs)            │
│                                                                 │
│  ┌──────────────┐    ┌──────────────┐    ┌──────────────┐     │
│  │ User Model   │───▶│ Permission   │───▶│ Permission   │     │
│  │              │    │ Cache Service│    │ Role Service │     │
│  └──────────────┘    └──────────────┘    └──────────────┘     │
│         │                   │                    │             │
│         │                   │                    │             │
│         ▼                   ▼                    ▼             │
│  ┌──────────────────────────────────────────────────────┐     │
│  │              Redis (Cache + Queue + Streams)          │     │
│  └──────────────────────────────────────────────────────┘     │
└─────────────────────────────────────────────────────────────────┘
                              │
                              │ user_id (FK)
                              ▼
┌─────────────────────────────────────────────────────────────────┐
│                         School Domain                           │
│  (Students, Classes, Grades, Schedules, Attendance, etc.)      │
│                                                                 │
│  ┌──────────────┐    ┌──────────────┐    ┌──────────────┐     │
│  │ Student      │───▶│ Class        │───▶│ Grade        │     │
│  │ Model        │    │ Model        │    │ Model        │     │
│  └──────────────┘    └──────────────┘    └──────────────┘     │
│                                                                 │
│  ┌──────────────┐    ┌──────────────┐    ┌──────────────┐     │
│  │ Schedule     │    │ Attendance   │    │ Discipline   │     │
│  │ Model        │    │ Model        │    │ Model        │     │
│  └──────────────┘    └──────────────┘    └──────────────┘     │
└─────────────────────────────────────────────────────────────────┘
                              │
                              │ user_id (FK)
                              ▼
┌─────────────────────────────────────────────────────────────────┐
│                        Finance Domain                           │
│  (Invoices, Payments, Fee Types)                               │
│                                                                 │
│  ┌──────────────┐    ┌──────────────┐    ┌──────────────┐     │
│  │ Invoice      │───▶│ Payment      │───▶│ FeeType      │     │
│  │ Model        │    │ Model        │    │ Model        │     │
│  └──────────────┘    └──────────────┘    └──────────────┘     │
│                                                                 │
│  ⚠️ CHƯA CÓ HTTP LAYER (Controllers/Routes)                    │
└─────────────────────────────────────────────────────────────────┘
```

## 7. Service Layer Wiring (AppServiceProvider)

`app/Providers/AppServiceProvider.php` là trung tâm wiring DI:

### Category A - Legacy (non-functional)
- Binds `App\Repositories\Contracts\*` → `App\Repositories\Eloquent\*`
- Binds `App\Services\Interface\*` → `App\Services\*`
- ⚠️ Các thư mục `app/Services/` và `app/Repositories/` **KHÔNG TỒN TẠI**

### Category B - Active Domain Bindings
- `App\Domains\School\Repositories\Contracts\*` → `App\Domains\School\Repositories\Eloquent\*`
- `App\Domains\School\Services\Interface\*` → `App\Domains\School\Services\*`
- `App\Domains\Identity\Repositories\Contracts\*` → `App\Domains\Identity\Repositories\Eloquent\*`
- `App\Domains\Identity\Services\Interface\*` → `App\Domains\Identity\Services\*`
- ⚠️ **Finance domain bindings HOÀN TOÀN THIẾU**

## 8. Vấn đề kiến trúc nghiêm trọng

### Critical Issues
1. **Missing `finance` DB connection** - Gây lỗi runtime cho tất cả Finance models
2. **Missing legacy directories** - `app/Services/` và `app/Repositories/` không tồn tại nhưng được bind trong container
3. **Finance domain không có HTTP layer** - Không có controllers, routes, request classes
4. **AuthServiceProvider trống** - Không đăng ký policies dù có 12 policy files

### High Priority Issues
5. **Duplicate JwtMiddleware** - Hai file trùng lặp, legacy copy là dead code
6. **StudentController là skeleton** - Tất cả methods đều empty stubs
7. **Incomplete migration** - `api.php.bak` vẫn còn trong repo
8. **Hardcoded captcha token** - Dùng literal string thay vì validation thực
9. **Permission cache TTL 300s** - Có thể gây stale permissions sau khi thay đổi role

### Medium Issues
10. **Mixed naming conventions** - `AuthServices` (plural) vs `AuthService` (singular)
11. **Unused HasRolePermission trait** - Được định nghĩa nhưng không apply vào model nào
12. **Duplicate dependency** - `knuckleswtf/scribe` xuất hiện cả trong `require` và `require-dev`
