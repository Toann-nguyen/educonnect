# 📋 Danh Sách Nhiệm Vụ (Tasks): Tái Cấu Trúc Luồng Đăng Ký Chuẩn Production (JWT & Redis)

Tài liệu này phân rã các bước thực hiện luồng đăng ký (Register) an toàn, hiệu suất cao theo đặc tả trong [register.md](file:///home/robert/educonnect/docs/Lggic/Auth/register.md) dành cho các Agent phát triển tiếp theo.

---

## 🗺️ Sơ Đồ Luồng Hoạt Động (ASCII Diagram)

```text
                     [Client Request]
                            │ (POST /register + Idempotency-Key)
                            ▼
               [Rate Limiting Middleware] ──(Vượt giới hạn 5 req/min)──► [429 Too Many Requests]
                            │ (Hợp lệ)
                            ▼
               [Idempotency Middleware] ──(Key tồn tại trong Redis)───► [Trả về Response cũ từ Cache]
                            │ (Key chưa tồn tại)
                            ▼
                   [RegisterRequest] ─────(Dữ liệu không hợp lệ)─────► [422 Validation Error]
                            │ (Hợp lệ)
                            ▼
                 [RegisterService / DB]
            ┌───────────────┴───────────────┐
            ▼ (Kiểm tra email tồn tại)       ▼ (Database Transaction)
     [Email Đã Tồn Tại]              [Tạo User (Status: UNVERIFIED)]
            │                        [Tạo Email Verification Token]
            ▼                               │
    [Generic Error]                         ▼
(Registration failed)        [Dispatch SendVerificationEmail Job]
                             [Publish UserRegistered Event]
                                            │
                                            ▼
                                    [Return 201 Created] (Không Auto-login)
                                    [Lưu Response vào Redis (TTL 24h)]
```

---

## 🛠️ Danh Sách File Liên Quan

- **Tạo mới**:
  - `app/Http/Middleware/IdempotencyMiddleware.php`
  - `app/Events/UserRegistered.php`
- **Chỉnh sửa**:
  - `routes/api.php`
  - `app/Http/Controllers/AuthController.php`
  - `app/Services/Auth/RegisterService.php` (hoặc `AuthService.php` nếu cần gộp)
  - `app/Http/Requests/Auth/RegisterRequest.php`
  - `tests/Feature/Auth/RegistrationTest.php`

---

## 📝 Danh Sách Nhiệm Vụ Chi Tiết (Checklist)

### Task 1: Thiết Lập Rate Limiting Cho Endpoint Register
- [ ] Định nghĩa một custom rate limiter cho luồng đăng ký trong `app/Providers/AppServiceProvider.php` hoặc cấu hình route:
  - Giới hạn tối đa: **5 requests/phút** trên mỗi địa chỉ IP.
  - Redis key format: `rate_limit:register:{ip}`.
- [ ] Áp dụng middleware này vào route `POST /api/auth/register` trong `routes/api.php` thay cho cấu hình không giới hạn hiện tại.
- [ ] Viết test case trong `RegistrationTest.php` để kiểm tra việc trả về status `429 Too Many Requests` khi gửi request thứ 6 liên tục.

### Task 2: Hiện Thực Idempotency Middleware (Chống trùng lặp dữ liệu)
- [ ] Tạo middleware `app/Http/Middleware/IdempotencyMiddleware.php` để xử lý header `Idempotency-Key`:
  - Kiểm tra xem header `Idempotency-Key` có tồn tại trong request hay không.
  - Nếu có: Kiểm tra trong Redis key `idempotency:{key}`. Nếu tồn tại, trả về ngay response đã được lưu từ trước (bao gồm HTTP status code, headers, và body content).
  - Nếu chưa có hoặc xử lý lần đầu: Tiến hành chuyển request tới controller/action tiếp theo. Sau khi nhận được response thành công (status `201`), lưu response này vào Redis với TTL là **24 giờ**.
- [ ] Đăng ký middleware này trong `app/Http/Kernel.php` (hoặc bootstrap config trên Laravel 11+) với bí danh `idempotence`.
- [ ] Áp dụng middleware `idempotence` vào route `POST /api/auth/register`.
- [ ] Viết integration test để giả lập việc gửi 2 request đăng ký giống hệt nhau có cùng một `Idempotency-Key` liên tiếp:
  - Request 1: Tạo tài khoản thành công, trả về HTTP 201.
  - Request 2: Trả về HTTP 201 ngay lập tức từ cache, kiểm tra DB chỉ có đúng 1 bản ghi user được tạo.

### Task 3: Cải Tiến Kiểm Tra Email & Tăng Cường Bảo Mật (No Email Enumeration)
- [ ] Chuyển luồng đăng ký của `AuthController@register` sang gọi trực tiếp qua `RegisterService` (hiện tại đang gọi `AuthService@register`).
- [ ] Tại `RegisterService@register`:
  - Thực hiện kiểm tra sự tồn tại của email qua `AuthRepositoryInterface`.
  - **Security Requirement**: Nếu email đã tồn tại, throw ra lỗi chung chung (Generic Exception) để tránh lộ thông tin email đã đăng ký (Email Enumeration Attack).
  - Thông báo trả về HTTP 409 hoặc 422 với nội dung an toàn: `"Registration failed. Please try again."` hoặc `"Registration failed."`. Không thông báo cụ thể `"Email already registered"`.
- [ ] Cập nhật `RegisterRequest` để kiểm tra password mạnh (tối thiểu 8 ký tự, có chữ hoa, chữ thường, số).

### Task 4: Xử Lý Tạo Tài Khoản Và Token Trong Transaction
- [ ] Đảm bảo toàn bộ quá trình tạo User và bản ghi token xác thực email được thực hiện trong một **Database Transaction** duy nhất (để tránh tình trạng tạo User thành công nhưng ghi đè token lỗi hoặc ngược lại).
  - Tạo user mới với trạng thái mặc định: `is_email_verified = false`.
  - Sinh token ngẫu nhiên và an toàn bằng `Str::random(64)`.
  - Lưu hash của token (SHA256) vào bảng `email_verifications` với thời gian hết hạn là **24 giờ**.
- [ ] Sau khi Transaction kết thúc thành công, dispatch Job gửi email xác nhận.

### Task 5: Triển Khai Xử Lý Bất Đồng Bộ (Asynchronous Queue Job & Events)
- [ ] Kiểm tra và đảm bảo Job `SendVerificationEmail` chạy trên Redis Queue (queue `emails`).
  - Thiết lập thuộc tính retry cho Job (ví dụ: `public $tries = 3`) và xử lý logic khi thất bại hoàn toàn (`failed()` method).
- [ ] Tạo Event `App\Events\UserRegistered` và phát đi (dispatch) ngay sau khi đăng ký thành công.
  - Event payload: `{ user_id, email, name, registered_at }`.
  - Cho phép các module/service phụ (như Gửi thư chào mừng, Đồng bộ Analytics) đăng ký lắng nghe event này mà không làm block phản hồi chính của API đăng ký.

### Task 6: Tối Ưu Phản Hồi API (Loại Bỏ Auto-Login)
- [ ] **Sửa đổi logic**: Không tự động sinh JWT Access Token khi đăng ký (loại bỏ bước auto-login). Người dùng bắt buộc phải xác minh email trước khi đăng nhập lần đầu.
- [ ] Phản hồi trả về ngay lập tức dạng `201 Created` với cấu trúc JSON chuẩn:
  ```json
  {
    "message": "Registration successful. Please check your email for verification.",
    "data": {
      "id": 1,
      "email": "user@example.com",
      "status": "UNVERIFIED"
    }
  }
  ```
- [ ] Cập nhật lại các test case cũ trong `RegistrationTest.php` để loại bỏ kiểm tra sinh JWT token trả về khi đăng ký và đảm bảo test chạy pass hoàn toàn.

---

## ⚠️ Các Điểm Cần Lưu Ý Khi Kiểm Tra (Verification Criteria)

1. **Chạy Test Suite**: Chạy `php artisan test --filter=RegistrationTest` phải thành công 100%.
2. **Rate Limiting**: Dùng Postman hoặc curl gọi liên tục 6 lần để kiểm tra mã trạng thái HTTP 429.
3. **Idempotency**: Gửi 2 request trùng header `Idempotency-Key` xem DB có bị trùng lặp user hay không và thời gian phản hồi của request thứ 2 có dưới 50ms không (do được lấy từ Redis Cache).
4. **Queue Worker**: Chạy `php artisan queue:work` để xác nhận job gửi mail được đưa vào hàng đợi đúng cách và xử lý thành công.
