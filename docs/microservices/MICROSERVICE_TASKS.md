# KẾ HOẠCH TÁCH MICROSERVICES — EDUCONNECT

## 1. MỤC TIÊU
- Tách monolith ~/educonnect (Laravel 10) thành 4 service, LÀM LUÔN TRONG repo ~/educonnect/ (không tạo thư mục riêng). Chuẩn công cụ theo plan v3: k3s, NGINX Ingress, Redis Stream,RabitMQ,  MySQL StatefulSet, Jenkins+ArgoCD, Vault.
- 4 service: identity (PHP/Laravel), school (PHP/Laravel), finance (GO), notify (GO).

## 2. CẤU TRÚC TRONG REPO
~/educonnect/
├── app/Domains/
│   ├── Identity/  (Models, Services, Controllers, Routes auth)
│   ├── School/    (sau: Student/Class/Subject/Grade...)
│   ├── Finance/   (sau: Go module, hoặc tách riêng container)
│   └── Notify/    (sau)
├── routes/identity.php, school.php, finance.php, notify.php
├── database/migrations/identity/, school/, finance/
├── docs/MICROSERVICE_TASKS.md
└── (giữ nguyên app/Http cũ trong quá trình chuyển đổi)

## 3. THỨ TỰ TÁCH (Strangler Fig)
- B1: Identity — ĐÃ LÀM Ở ~/educonnect-identity/ (riêng). Kế hoạch: merge code vào ~/educonnect/app/Domains/Identity/, xóa ~/educonnect-identity/. Auth dùng jwt-auth HS256, spatie RBAC, DB identity_db.
- B2: School — tách Student/Class/Subject/Schedule/Attendance/Grade/Discipline/Event/Library từ monolith vào app/Domains/School/. DB school_db. (ĐÃ khảo sát: 14 models, 14 controllers, KHÔNG gọi cross-service, chỉ link user_id).
- B3: Finance (GO) — tách Fees/Invoice/Payment. DB finance_db. Giao tiếp qua Redis Stream event UserCreated.
- B4: Notify (GO) — consumer Redis Stream gửi mail/SMS.

## 4. DATABASE PER-SERVICE
- identity_db (MySQL), school_db (MySQL), finance_db (MySQL/Postgres), notify (Redis).
- ETL: dump bảng từ monolith educonnect_db sang DB riêng. Dual-write tạm thời, cut-over dần.
- Monolith gốc vẫn chạy đến khi toàn bộ traffic qua domain routes.

## 5. API GATEWAY (NGINX Ingress theo v3)
- /api/auth/* → Identity domain
- /api/school/* → School domain
- /api/finance/* → Finance (Go)
- /api/notify/* → Notify
- Frontend Next.js giữ nguyên api-client.ts, chỉ đổi NEXT_PUBLIC_API_URL trỏ Gateway.

## 6. VERIFICATION
- Mỗi domain: route:list không lỗi, curl test endpoints (login=200, /api/classes=200...).
- SSO: 1 token dùng cross-service.
- Eventual consistency: tạo user → finance nhận event.

## 7. TRẠNG THÁI HIỆN TẠI
- ~/educonnect/ monolith: NGUYÊN BẢN (chưa sửa).
- ~/educonnect-identity/: ĐÃ TÁCH XONG (B1, login=200, 4user/8role/8perm ETL). Sẽ merge vào repo gốc.
- School: ĐÃ KHẢO SÁT, chưa code.

## 8. RỦI RO & QUYẾT ĐỊNH CẦN CHỐT
- Finance/Notify dùng Go (không Node) — xác nhận.
- Có giữ nguyên PHP cho Finance/Notify không, hay bắt buộc Go? (Chốt: chỉ thêm Go cho Finance).
- Xóa ~/educonnect-identity/ sau khi merge.

## 9. CHI TIẾT TRIỂN KHAI MERGE IDENTITY & DATABASE (BỔ SUNG)

### A. Quy trình Merge Code Identity
1. **Sao chép và Tổ chức File**:
   - Copy các thư mục nghiệp vụ từ `~/educonnect-identity/app/` vào `~/educonnect/app/Domains/Identity/` (gồm Models, Http/Controllers, Http/Requests, Http/Resources, Services, Repositories, Jobs, Events, Exceptions, Mail).
2. **Refactor Namespace**:
   - Chuyển namespace của toàn bộ file được copy sang prefix `App\Domains\Identity\...` (ví dụ: `App\Domains\Identity\Models\User`).
3. **Cập nhật config/auth.php**:
   - Trỏ provider `users.model` sang `App\Domains\Identity\Models\User::class`.
4. **Dọn dẹp File Monolith cũ**:
   - Xóa các file model cũ trong `app/Models/` trùng lặp (User, Role, Permission, Profile, BackupCode, UserSession, RefreshToken, PasswordResetToken, AuditLog, EmailVerification).
5. **Cập nhật tham chiếu toàn dự án**:
   - Đổi `use App\Models\User;` thành `use App\Domains\Identity\Models\User;` trong toàn bộ phần còn lại của monolith.

### B. Cấu hình Database & Migrations per-service
1. **Cấu hình Kết nối Database** (`config/database.php`):
   - Thêm 2 kết nối riêng biệt `identity` (trỏ tới `identity_db`) và `school` (trỏ tới `school_db`).
2. **Khai báo `$connection` trong Model**:
   - Tất cả các Model trong `App\Domains\Identity\Models` khai báo `protected $connection = 'identity';`.
   - Các Model trong phân hệ School khai báo `protected $connection = 'school';`.
3. **Tách thư mục Migrations**:
   - Di chuyển migrations tương ứng vào `database/migrations/identity/` và `database/migrations/school/`.
   - Chạy lệnh migration theo thư mục:
     ```bash
     php artisan migrate --database=identity --path=database/migrations/identity
     php artisan migrate --database=school --path=database/migrations/school
     ```
4. **Reset Spatie RBAC Cache**:
   - Chạy `php artisan permission:cache-reset` sau khi refactor namespace model User/Role/Permission để tránh lỗi cache cũ.

## 10. DANH SÁCH FILE HƯỚNG DẪN CHI TIẾT (TASK GUIDES FOR AGENTS)
Dành cho AI Agent hoặc Dev thực hiện từng nhiệm vụ nhỏ:
- 📄 **Task 1: Merge Identity Service**: [task_1_identity_merge.md](file:///home/robert/educonnect/docs/microservices/task_1_identity_merge.md)
- 📄 **Task 2: Setup Per-Service Database & Migrations**: [task_2_database_split.md](file:///home/robert/educonnect/docs/microservices/task_2_database_split.md)
- 📄 **Task 3: Production Docker & Nginx Deployment**: [task_3_production_deploy.md](file:///home/robert/educonnect/docs/microservices/task_3_production_deploy.md)



