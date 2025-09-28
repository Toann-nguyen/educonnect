## Schedule

kiểm tra xung đột (Conflict Checking)

    Vấn đề: Hệ thống hiện tại cho phép bạn tạo ra các lịch học bị xung đột, ví dụ:

        Cùng một giáo viên được xếp dạy ở 2 lớp khác nhau trong cùng một thời điểm.

        Cùng một lớp học được xếp học 2 môn khác nhau trong cùng một thời điểm.

        Cùng một phòng học được sử dụng bởi 2 lớp khác nhau trong cùng một thời điểm.

    Cần làm:

        Trong StoreScheduleRequest và UpdateScheduleRequest: Thêm các quy tắc validation tùy chỉnh (custom validation rules).

        Trước khi lưu một tiết học mới, hãy kiểm tra trong bảng schedules xem có tồn tại bản ghi nào có cùng teacher_id, class_id, hoặc room tại cùng day_of_week và period hay không. Nếu có, trả về lỗi validation.

3.  Chức năng "Sao chép Thời khóa biểu" (Copy Schedule)

    Vấn đề: Việc nhập liệu thủ công TKB cho 15-20 lớp học là rất tốn thời gian.

    Cần làm: Tạo một chức năng cho phép Admin:

        Chọn một "lớp mẫu" (ví dụ: 10A1) đã có TKB hoàn chỉnh.

        Chọn một hoặc nhiều "lớp đích" (ví dụ: 10A2, 10A3...).

        Nhấn nút "Sao chép". Hệ thống sẽ tự động tạo ra các bản ghi schedule mới cho các lớp đích, dựa trên TKB của lớp mẫu.

        Endpoint có thể là: POST /api/schedules/copy

4.  Chức năng "Hoán đổi Giáo viên" (Swap Teacher)

    Vấn đề: Khi một giáo viên nghỉ ốm, Admin cần nhanh chóng tìm và gán một giáo viên khác dạy thay cho tất cả các tiết của giáo viên đó trong ngày/tuần.

    Cần làm: Tạo một chức năng cho phép Admin:

        Chọn "Giáo viên nghỉ".

        Chọn "Giáo viên dạy thay".

        Chọn khoảng thời gian (ví dụ: từ ngày X đến ngày Y).

        Nhấn nút "Hoán đổi". Hệ thống sẽ cập nhật teacher_id cho tất cả các bản ghi schedule phù hợp.

        Endpoint có thể là: POST /api/schedules/swap-teacher

5.  Giao diện xem tổng hợp (Master View)

    Vấn đề: Admin cần một cái nhìn tổng quan để biết giáo viên nào đang rảnh, phòng học nào đang trống.

    Cần làm: Tạo các giao diện xem TKB không chỉ theo lớp, mà còn:

        Xem theo Giáo viên: Hiển thị TKB của một giáo viên cụ thể.

        Xem theo Phòng học: Hiển thị TKB của một phòng học cụ thể.

## schedule ,dashboard ,

Tổng hợp các URL của API EduConnect

1. Nhóm API Xác thực (Public - Không cần Token)
   Chức năng Method URL Body (JSON) cần thiết
   Đăng ký tài khoản POST /api/auth/register full_name, email, password, password_confirmation
   Đăng nhập POST /api/auth/login email, password
   Gửi yêu cầu quên mật khẩu POST /api/auth/forgot-password email
   Đặt lại mật khẩu POST /api/auth/reset-password token, email, password, password_confirmation
2. Nhóm API Chung (Cần Token - Mọi vai trò đã đăng nhập)
   Chức năng Method URL Ghi chú
   Đăng xuất POST /api/auth/logout Xóa token hiện tại
   Lấy thông tin User GET /api/auth/user Lấy thông tin của chính người đang đăng nhập
   Lấy dữ liệu Dashboard GET /api/dashboard
   Xem hồ sơ cá nhân GET /api/profile
   Cập nhật hồ sơ PUT /api/profile
   Tải ảnh đại diện POST /api/profile/avatar
   Xem danh sách Sự kiện GET /api/events
   Xem chi tiết Sự kiện GET /api/events/{event} {event} là ID của sự kiện
   Đăng ký tham gia Sự kiện POST /api/events/{event}/register
   Xem danh sách Điểm danh GET /api/attendances
   Xem chi tiết Điểm danh GET /api/attendances/{attendance}
   Xem điểm danh của 1 HS GET /api/attendances/student/{student} {student} là ID của học sinh
3. Nhóm API theo Vai trò (Cần Token và Phân quyền)
   Vai trò yêu cầu Chức năng Method URL
   Admin Lấy danh sách Users GET /api/admin/users
   Tạo User POST /api/admin/users
   Xem chi tiết User GET /api/admin/users/{user}
   Cập nhật User PUT /api/admin/users/{user}
   Xóa User (Soft Delete) DELETE /api/admin/users/{user}
   Khôi phục User POST /api/admin/users/{id}/restore
   CRUD Năm học GET, POST, PUT, DELETE /api/admin/academic-years
   CRUD Lớp học GET, POST, PUT, DELETE /api/admin/classes
   CRUD Môn học GET, POST, PUT, DELETE /api/admin/subjects
   Quản lý Sự kiện POST, PUT, DELETE /api/events, /api/events/{event}

Teacher (hoặc Admin) CRUD Điểm số GET, POST, PUT, DELETE /api/grades
CRUD Thời khóa biểu GET, POST, PUT, DELETE /api/schedules
Lấy danh sách lớp tôi dạy GET /api/my-classes
Quản lý Điểm danh POST, PUT, DELETE /api/attendances, /api/attendances/{attendance}
Student, Parent Xem điểm của tôi/con tôi GET /api/my-grades
Xem hóa đơn của tôi/con tôi GET /api/my-invoices
Parent Xem danh sách con cái GET /api/my-children
Người có quyền
record discipline CRUD Kỷ luật GET, POST, PUT, DELETE /api/disciplines
manage finances CRUD Hóa đơn GET, POST, PUT, DELETE /api/invoices
CRUD Thanh toán GET, POST, PUT, DELETE /api/payments
Xem báo cáo tài chính GET /api/financial-reports
manage library CRUD Sách GET, POST, PUT, DELETE /api/library-books
CRUD Giao dịch thư viện GET, POST, PUT, DELETE /api/library-transactions
manage events Quản lý Sự kiện POST, PUT, DELETE /api/events, /api/events/{event

## Các chức năng chính của Phụ huynh

    Xem danh sách con của mình: GET /api/my-children

    Xem thời khóa biểu của con: GET /api/schedules/class/{class_id} (đã có)

    Xem bảng điểm của con: GET /api/my-grades (đã có, sẽ được GradeService xử lý)

    Xem hóa đơn của con: GET /api/my-invoices

    Xin nghỉ phép cho con: POST /api/leave-requests
