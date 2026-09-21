# BÁO CÁO ĐÁNH GIÁ & KẾ HOẠCH TÁCH MICROSERVICES
**Dự án:** Laravel Monolith -> Microservices (Laravel & Go)  
**Tác giả:** Nguyễn Minh Toàn  
**Ngày lập:** 27/07/2026  
**Trạng thái:** Tách miền một phần (Partially Completed)  

---

## 1. TỔNG QUAN HIỆN TRẠNG (PROJECT STATUS)

Hệ thống đang trong giai đoạn tiền phân tách (Pre-extraction Phase). Kiến trúc code đã bắt đầu chuyển đổi theo dạng **Domain-Driven Design (DDD)** nội bộ trong Laravel Monolith, kết hợp hai service độc lập bằng **Go** (Finance và Notification).

* **Identity Domain:** Đã gom nhóm vào `app/Domains/Identity/` với kết nối DB riêng (`identity`).
* **School Domain:** Đã gom nhóm vào `app/Domains/School/` với kết nối DB riêng (`school`).
* **Go Services:** Tách rời hoàn toàn (`services/finance/`, `services/notify/`), điều hướng qua Nginx Proxy.

---

## 2. NHỮNG ĐIỂM ĐÃ ĐÚNG — KHÔNG CẦN SỬA (KEEP AS IS)

Các phần dưới đây đã đạt chuẩn về mặt kiến trúc và cấu hình ban đầu, **giữ nguyên không can thiệp**:

| Thành phần | Hiện trạng | Đánh giá / Lý do giữ nguyên |
| :--- | :--- | :--- |
| **Go Services Isolation** | `services/finance/`, `services/notify/` | Đã tách biệt codebase và môi trường thực thi hoàn toàn với Monolith. |
| **Nginx Route Proxying** | Trỏ `/api/finance/` và `/api/notify/` sang Go | Đóng vai trò API Gateway ban đầu chuẩn xác, không bị phụ thuộc vào Laravel. |
| **Database Connections** | `$connection = 'identity'`, `$connection = 'school'` | Các Model đã khai báo đúng connection tương ứng trong `config/database.php`. |
| **Routes Splitting** | `routes/identity.php`, `routes/school.php` | Phân tách tập file routing rõ ràng theo ranh giới Domain. |
| **Migration Splitting** | `database/migrations/identity/`, `school/` | Đã phân tách thư mục quản lý schema riêng biệt theo từng DB. |
| **Empty `app/Models/`** | Thư mục `app/Models/` rỗng | Xóa hoặc để rỗng thư mục này là đúng định hướng Domain. Lỗi gãy code chỉ do import namespace sai. |

---

## 3. NHỮNG ĐIỂM SAI VÀ CẦN SỬA GẤP (CRITICAL FIXES & REFACTORING)

### 3.1. Sửa lỗi 188 Reference `App\Models` bị gãy (Broken Namespace References)
Do `app/Models/` đã bị dọn dẹp, toàn bộ các file import `App\Models` cũ đang bị lỗi fatal error tại runtime.

* **Repositories (`app/Repositories/`):** 9+ file đang gọi sai `App\Models\SchoolModel`.
* **Services (`app/Services/`):** 10+ file gọi sai Model, bao gồm `AuthService` đang dùng `App\Models\AuditLog`.
* **Policies (`app/Policies/`):** 17 file Policy đang import `App\Models\User` hoặc `App\Models\School`.
* **Seeders & Factories:** Tất cả file trong `database/seeders/` và `database/factories/` bị sai Namespace.
* **Tests (`tests/`):** Các file test Auth/User vẫn trỏ về `App\Models\User`.
* **Events Docblocks:** Duplicate và sai docblock ở `app/Domains/Identity/Events/UserRegistered.php` và `app/Events/UserRegistered.php`.

### 3.2. Đính chính lầm tưởng về `composer.json` Autoload
* **Đính chính:** Autoload `App\ -> app/` mapping namespace `App\` vào `app/`. Việc xóa folder `app/Models/` **không làm hỏng composer autoload**. Lỗi hiện tại 100% xuất phát từ việc gọi sai class name/namespace không tồn tại trên đĩa.

### 3.3. Sửa cấu trúc Repositories & Services nằm ngoài Domain
* **Hiện trạng:** `app/Repositories/` và `app/Services/` đang nằm lơ lửng ngoài cấu trúc Domain.
* **Giải pháp:** Di chuyển toàn bộ Repositories và Services tương ứng vào bên trong từng Domain cụ thể:
  * `app/Domains/Identity/Repositories/` & `Services/`
  * `app/Domains/School/Repositories/` & `Services/`

### 3.4. Rà soát & Làm sạch Migration Trùng Lặp
* Kiểm tra `database/migrations/identity/2014_10_12_000000_create_users_table.php` (file default của Laravel) với migration tạo bảng users trong `identity/`. Xóa file thừa để tránh trùng lặp schema.
* **Foreign Keys Cross-DB:** Xóa toàn bộ ràng buộc `foreignId()->constrained()` giữa 2 DB khác nhau trong Migrations (chẳng hạn `school` liên kết `user_id` sang `identity`).

---

## 4. KẾ HOẠCH TRIỂN KHAI THEO LỘ TRÌNH (STEP-BY-STEP ACTION PLAN)

```
[Giai đoạn 1: Refactoring Codebase] -> [Giai đoạn 2: Tách Ranh Giới Data] -> [Giai đoạn 3: Verify & Extraction]
```

### 📍 Giai đoạn 1: Refactoring Codebase & Sửa Lỗi Runtime (Ưu tiên cao nhất)
1. **Automated Namespace Replacement:**
   * Thay thế toàn bộ `App\Models\User` -> `App\Domains\Identity\Models\User`.
   * Thay thế toàn bộ các Model liên quan đến Trường học sang `App\Domains\School\Models`.
2. **Reorganize Architecture:**
   * Di chuyển Repositories, Contracts, Services, Policies vào đúng thư mục `app/Domains/{DomainName}/`.
3. **Dump Autoload & IDE Helper Update:**
   * Chạy `composer dump-autoload`.
   * Chạy `php artisan ide-helper:generate` và `php artisan ide-helper:models` để cập nhật lại autocomplete.

### 📍 Giai đoạn 2: Tách Ranh Giới Dữ Liệu & Eloquent Relationships
1. **Loại bỏ Cross-DB Eloquent Relationships:**
   * Không dùng `hasMany` / `belongsTo` trực tiếp giữa `User` (Identity DB) và `Schedule` (School DB).
   * Chuyển sang query dữ liệu theo `user_id` dạng nguyên thủy (Primitive ID lookup) hoặc gọi Service API.
2. **Xử lý Migration & Database Clean-up:**
   * Xóa các file migration trùng lặp trong `database/migrations/identity/`.
   * Đảm bảo không còn câu lệnh ràng buộc Foreign Key cứng giữa 2 DB.

### 📍 Giai đoạn 3: Kiểm thử & Chuẩn bị Tách Service
1. **Chạy Suite Test:**
   * Sửa các file test trong `tests/` và thực thi `php artisan test` đạt 100% PASS.
2. **Event & Message Broker Prep:**
   * Gom gọn các Event bị trùng (xóa `app/Events/UserRegistered.php`, chỉ giữ lại `app/Domains/Identity/Events/UserRegistered.php`).
   * Chuẩn bị tích hợp RabbitMQ khi tách hẳn `School Domain` ra container độc lập.

---

## 5. TỔNG KẾT CHECKLIST TRƯỚC KHAI THÁC (PRE-EXTRACTION CHECKLIST)

- [ ] Toàn bộ 188 reference `App\Models` đã được refactor sạch sẽ.
- [ ] Folder `app/Repositories/` đã được di chuyển vào các `Domains/`.
- [ ] Lệnh `composer dump-autoload` chạy không lỗi.
- [ ] Lệnh `php artisan test` đi qua toàn bộ test cases.
- [ ] Migration giữa `identity` và `school` không trùng lặp và không dính Cross-DB Foreign Keys.
- [ ] Đã chạy lại `php artisan ide-helper:models`.