+----------------+ +----------------+ +-----------------------+
| permissions |<---- | role_has_perm | ---->| roles |
+----------------+ +----------------+ +-----------------------+
^ ^
| |
+-------------+ +-----------------+
| (N-N) |
+----------v-----------+ |
| model_has_roles | |
+----------------------+ |
| (N-N) |
+-----------+ +----------+ v +-----------------+ | (1-N)
| profiles |<---- | users | ------> | students |<--| classes |<-+ (GVCN)
| (1-1) | | (CORE) | | (1-1 with user)| | (SchoolClass) |
+-----------+ +----------+ +-----------------+ +-----------------+
^ ^ ^ ^ ^
| | | | |
+------------------+ | | | | +---------+---------+
| student_guardian |-----+ | | | | academic_years |
| (N-N with user) | | | | +-------------------+
+------------------+ | | |
| | | +----------+
| +--------------+ +---->| schedules|<--+ subjects |
| | (Teacher) +----------+ +----------+
| | ^ ^ ^
| +-------------------------------+ | |
| | | |
+----------+ <-------------+ (Reporter) | | |
|disciplines | ------------+----------------------------------------+ | |
+----------+ (Student) (Student) | |
| | |
+----------+ <-------------+----------------------------------------------+---------+
| grades |---------------+----------------------------------------------+---------+
+----------+ (Student) (Subject)

================================== SUBSYSTEMS ==================================

+----------+ +-----------+ <----- users (Payer)
| invoices |----->| payments |
| (1-N) | | (N-1) |
+----------+ +-----------+
^
|
students (Debtor)

+-------------+ +----------------------+ <----- users (Borrower)
| library_books |---->| library_transactions |
| (1-N) | | (N-1) |
+-------------+ +----------------------+

+--------+ +---------------------+ <----- students (Registrant)
| events |----->| event_registrations |
| (1-N) | | (N-N) |
+--------+ +---------------------+

## cac role

Quản trị viên admin ✅ Có
Hiệu trưởng principal ✅ Có
Giáo viên teacher ✅ Có
Phụ huynh parent ✅ Có
Học sinh student ✅ Có
Cờ đỏ red_scarf ✅ Có
Kế toán accountant ✅ Có
Thủ thư librarian ✅ Có

## 1️⃣ Nhóm API Xác thực (Public - Không cần Token)

| Chức năng                 | Method | URL                         | Body (JSON) cần thiết                               |
| ------------------------- | ------ | --------------------------- | --------------------------------------------------- |
| Đăng ký tài khoản         | POST   | `/api/auth/register`        | `full_name, email, password, password_confirmation` |
| Đăng nhập                 | POST   | `/api/auth/login`           | `email, password`                                   |
| Gửi yêu cầu quên mật khẩu | POST   | `/api/auth/forgot-password` | `email`                                             |
| Đặt lại mật khẩu          | POST   | `/api/auth/reset-password`  | `token, email, password, password_confirmation`     |

## 2️⃣ Nhóm API Chung (Cần Token - Mọi vai trò đã đăng nhập)

Chức năng Method URL Ghi chú
Đăng xuất POST /api/auth/logout Xóa token hiện tại
Lấy thông tin User GET /api/auth/user Thông tin user đang đăng nhập
Lấy dữ liệu Dashboard GET /api/dashboard
Xem hồ sơ cá nhân GET /api/profile
Cập nhật hồ sơ PUT /api/profile
Tải ảnh đại diện POST /api/profile/avatar
Xem danh sách Sự kiện GET /api/events
Xem chi tiết Sự kiện GET /api/events/{event} {event} là ID sự kiện
Đăng ký tham gia Sự kiện POST /api/events/{event}/register
Xem danh sách Điểm danh GET /api/attendances
Xem chi tiết Điểm danh GET /api/attendances/{attendance}
Xem điểm danh của 1 HS GET /api/attendances/student/{student} {student} là ID học sinh

## Nhóm API theo Vai trò (Cần Token và Phân quyền)

👑 Admin
Chức năng Method(s) URL
Lấy danh sách Users GET /api/admin/users
Tạo User POST /api/admin/users
Xem chi tiết User GET /api/admin/users/{user}
Cập nhật User PUT /api/admin/users/{user}
Xóa User (Soft Delete) DELETE /api/admin/users/{user}
Khôi phục User POST /api/admin/users/{id}/restore
CRUD Năm học GET, POST, PUT, DELETE /api/admin/academic-years
CRUD Lớp học GET, POST, PUT, DELETE /api/admin/classes
CRUD Môn học GET, POST, PUT, DELETE /api/admin/subjects
Quản lý Sự kiện POST, PUT, DELETE /api/events, /api/events/{event}

## 👨‍🏫 Teacher (hoặc Admin)

Chức năng Method(s) URL
CRUD Điểm số GET, POST, PUT, DELETE /api/grades
CRUD Thời khóa biểu GET, POST, PUT, DELETE /api/schedules
Lấy danh sách lớp tôi dạy GET /api/my-classes
Quản lý Điểm danh POST, PUT, DELETE /api/attendances, /api/attendances/{attendance}

## 🎓 Student, Parent

Chức năng Method URL
Xem điểm của tôi/con tôi GET /api/my-grades
Xem hóa đơn của tôi/con tôi GET /api/my-invoices

## 👪 Parent

Chức năng Method URL
Xem danh sách con cái GET /api/my-children

## 🔑 Người có quyền (theo Permission)

Quyền Chức năng Method(s) URL
record discipline CRUD Kỷ luật GET, POST, PUT, DELETE /api/disciplines
manage finances CRUD Hóa đơn GET, POST, PUT, DELETE /api/invoices
CRUD Thanh toán GET, POST, PUT, DELETE /api/payments
Xem báo cáo tài chính GET /api/financial-reports
manage library CRUD Sách GET, POST, PUT, DELETE /api/library-books
CRUD Giao dịch thư viện GET, POST, PUT, DELETE /api/library-transactions
manage events Quản lý Sự kiện POST, PUT, DELETE /api/events, /api/events/{event}

```
## thoi khoa bieu
    Vai trò	Quyền hạn đối với Thời khóa biểu
    Admin / Principal	Toàn quyền (Full Control): Xem, Tạo, Sửa, Xóa thời khóa biểu cho BẤT KỲ lớp học nào.
    Teacher	Xem: Xem thời khóa biểu của các lớp mình được phân công giảng dạy và lớp mình chủ nhiệm.<br>Giới hạn: Không thể tự ý tạo/sửa/xóa (trừ khi được cấp quyền đặc biệt).
    Student	Chỉ xem (Read-only): Xem thời khóa biểu của lớp mình đang học.
    Parent	Chỉ xem (Read-only): Xem thời khóa biểu của lớp học của con mình.
```
