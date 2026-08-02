# Enums

## Global Enums

Enums are defined in `app/Enums/` and are shared across all domains.

---

## RoleEnum

**File**: `app/Enums/RoleEnum.php`
**Type**: String-backed enum

| Case | Value | Label (Vietnamese) |
|------|-------|-------------------|
| `ADMIN` | `admin` | Quản trị viên |
| `PRINCIPAL` | `principal` | Hiệu trưởng |
| `TEACHER` | `teacher` | Giáo viên |
| `STUDENT` | `student` | Học sinh |
| `PARENT` | `parent` | Phụ huynh |
| `ACCOUNTANT` | `accountant` | Kế toán |
| `LIBRARIAN` | `librarian` | Thủ thư |
| `RED_SCARF` | `red_scarf` | Đoàn viên |

### Methods

| Method | Return | Description |
|--------|--------|-------------|
| `label()` | `string` | Returns Vietnamese label for the role |
| `values()` | `array` | Returns all role values as strings |

### Usage

```php
use App\Enums\RoleEnum;

RoleEnum::ADMIN->value;     // 'admin'
RoleEnum::ADMIN->label();   // 'Quản trị viên'
RoleEnum::values();         // ['admin', 'principal', 'teacher', ...]
```

---

## PermissionEnum

**File**: `app/Enums/PermissionEnum.php`
**Type**: String-backed enum

### User Management

| Case | Value | Label (Vietnamese) |
|------|-------|-------------------|
| `MANAGE_USERS` | `manage_users` | Quản lý người dùng |
| `VIEW_USERS` | `view_users` | Xem danh sách người dùng |
| `CREATE_USERS` | `create_users` | Tạo người dùng |
| `EDIT_USERS` | `edit_users` | Chỉnh sửa người dùng |
| `DELETE_USERS` | `delete_users` | Xóa người dùng |

### Role & Permission Management

| Case | Value | Label (Vietnamese) |
|------|-------|-------------------|
| `MANAGE_ROLES` | `manage_roles` | Quản lý vai trò |
| `MANAGE_PERMISSIONS` | `manage_permissions` | Quản lý quyền hạn |

### School Structure

| Case | Value | Label (Vietnamese) |
|------|-------|-------------------|
| `MANAGE_SCHOOL_STRUCTURE` | `manage_school_structure` | Quản lý cấu trúc trường |
| `MANAGE_CLASSES` | `manage_classes` | Quản lý lớp học |
| `MANAGE_ACADEMIC_YEARS` | `manage_academic_years` | Quản lý năm học |

### Schedule & Grade

| Case | Value | Label (Vietnamese) |
|------|-------|-------------------|
| `MANAGE_SCHEDULES` | `manage_schedules` | Quản lý thời khóa biểu |
| `VIEW_SCHEDULES` | `view_schedules` | Xem thời khóa biểu |
| `MANAGE_GRADES` | `manage_grades` | Quản lý điểm số |
| `VIEW_GRADES` | `view_grades` | Xem điểm số |
| `MANAGE_ATTENDANCE` | `manage_attendance` | Quản lý điểm danh |
| `VIEW_ATTENDANCE` | `view_attendance` | Xem điểm danh |

### Finance

| Case | Value | Label (Vietnamese) |
|------|-------|-------------------|
| `MANAGE_FINANCES` | `manage_finances` | Quản lý tài chính |
| `VIEW_INVOICES` | `view_invoices` | Xem hóa đơn |
| `MANAGE_INVOICES` | `manage_invoices` | Quản lý hóa đơn |
| `MANAGE_PAYMENTS` | `manage_payments` | Quản lý thanh toán |

### Library

| Case | Value | Label (Vietnamese) |
|------|-------|-------------------|
| `MANAGE_LIBRARY` | `manage_library` | Quản lý thư viện |
| `VIEW_LIBRARY` | `view_library` | Xem thư viện |
| `BORROW_BOOKS` | `borrow_books` | Mượn sách |

### Discipline

| Case | Value | Label (Vietnamese) |
|------|-------|-------------------|
| `RECORD_DISCIPLINE` | `record_discipline` | Ghi nhận kỷ luật |
| `MANAGE_DISCIPLINE` | `manage_discipline` | Quản lý kỷ luật |
| `VIEW_DISCIPLINE` | `view_discipline` | Xem kỷ luật |

### Events

| Case | Value | Label (Vietnamese) |
|------|-------|-------------------|
| `MANAGE_EVENTS` | `manage_events` | Quản lý sự kiện |
| `VIEW_EVENTS` | `view_events` | Xem sự kiện |
| `REGISTER_EVENTS` | `register_events` | Đăng ký sự kiện |

### Methods

| Method | Return | Description |
|--------|--------|-------------|
| `label()` | `string` | Returns Vietnamese label for the permission |
| `values()` | `array` | Returns all permission values as strings |

### Usage

```php
use App\Enums\PermissionEnum;

PermissionEnum::MANAGE_USERS->value;  // 'manage_users'
PermissionEnum::MANAGE_USERS->label(); // 'Quản lý người dùng'
PermissionEnum::values();              // ['manage_users', 'view_users', ...]
```

## Enum Design Patterns

### String-Backed Enums
Both enums use string backing (`enum RoleEnum: string`), which allows direct comparison with database values and API responses.

### Label Method
Each enum case has a Vietnamese human-readable label via the `label()` method, following the `match` expression pattern.

### Values Helper
The static `values()` method returns all enum values as an array, useful for validation and dropdown options.

### Integration with Spatie Permission
Permission enum values map directly to Spatie permission names stored in the `permissions` table. Roles are assigned using Spatie's `assignRole()` and `hasRole()` methods.