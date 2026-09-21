# 🤖 Hướng dẫn thực thi cho AI Agent: Giai đoạn 3 & 4 (Cập nhật theo thực tế)

> **Lưu ý**: Hướng dẫn này dựa trên trạng thái hiện tại của project (commit `9e4dbb0`).
> Đừng tạo lại các file đã tồn tại — chỉ hoàn thiện và verify.

---

## 🎯 Mục tiêu tổng quát
1. **Giai đoạn 3**: Hoàn thiện Go Microservices (Finance, Notify) — verify consumer RabbitMQ, tạo HTTP Client trong PHP để gọi Go Finance, fix DB connection.
2. **Giai đoạn 4**: Verify Nginx Gateway, tạo API Contract (Swagger) cho Go Finance, dọn dẹp Backend (dead code, namespace).

---

## ⚙️ Giai đoạn 3: Hoàn thiện Go Microservices & Tích hợp Event Bus

### Bước 3.1: Verify RabbitMQ & Go Consumers (Test luồng Event Bus)
**Nhiệm vụ**: Kiểm tra xem Go `notify` và `finance` có đang consume đúng exchange không.

**Hành động**:
1. Truy cập RabbitMQ Management UI (`@url:`http://localhost:15672``, user: `educonnect`, pass: từ `.env`).
2. Vào tab **Exchanges** → **`user_events`** → Kiểm tra **Bindings**:
   - Phải có binding `user.#` → `notification_queue`
3. Vào tab **Queues** → **`notification_queue`** → Kiểm tra **Consumers** (phải ≥ 1, từ Go `notify`).
4. **Test giả lập**:
   - Vào Exchange `user_events` → **Publish message**:
     - Routing key: `user.registered`
     - Payload: `{"user":{"id":1,"email":"test@edu.com","name":"Test User"}}`
   - Check log: `docker compose -f docker-compose.yml logs -f notify`

**✅ Acceptance Criteria**: Log Go `notify` hiển thị "sync user.registered → users_read_model".

---

### Bước 3.2: Hoàn thiện Go Finance Service (HTTP API + Consumer)
**Nhiệm vụ**: Đảm bảo Go Finance vừa expose HTTP API (để PHP gọi), vừa consume event từ RabbitMQ.

**Hành động**:
1. Mở `services/finance/main.go`.
2. **Kiểm tra HTTP Router** (đã dùng `gin`):
   - Endpoint `GET /health` (đã có, line 248).
   - Endpoint `GET /api/finance/fee-types` (đã có, line ~256).
   - Endpoint `POST /api/finance/fee-types` (đã có).
   - **Cần thêm**: `GET /api/v1/students/:id/invoices` để PHP gọi lấy danh sách hóa đơn.
3. **Kiểm tra Consumer**:
   - Đã có: goroutine `consume(ch)` lắng nghe queue từ exchange `finance_events`.
   - Xử lý event `invoice.created`, `payment.success` để cập nhật `finance_db`.
4. **Build & Test**:
   ```bash
   cd /home/robert/educonnect/services/finance && go build -o finance .
   ```
5. **Chạy trong Docker**:
   ```bash
   docker compose -f docker-compose.yml up -d --build finance
   docker compose -f docker-compose.yml logs -f finance
   ```
**✅ Acceptance Criteria**:
- `curl http://localhost:8080/health` trả về `{"service":"finance-service","status":"healthy"}`.
- Log `finance` hiển thị consumer đang lắng nghe.

---

### Bước 3.3: Tạo PHP HTTP Client gọi sang Go Finance (Bước B7)
**Nhiệm vụ**: Tạo Service trong Laravel để gọi HTTP sang Go Finance khi cần dữ liệu đồng bộ.

**Hành động**:
1. Tạo file `app/Domains/Finance/Services/FinanceApiClient.php`:
   ```php
   <?php

   namespace App\Domains\Finance\Services;

   use Illuminate\Support\Facades\Http;
   use Illuminate\Support\Facades\Log;

   class FinanceApiClient
   {
       protected string $baseUrl;

       public function __construct()
       {
           // Gọi nội bộ qua Docker network, không cần qua Nginx
           $this->baseUrl = rtrim(env('FINANCE_SERVICE_URL', 'http://finance:8080'), '/');
       }

       public function getInvoicesByStudent(int $studentId): array
       {
           try {
               $response = Http::withHeaders([
                   'Authorization' => request()->header('Authorization'),
                   'Accept' => 'application/json',
               ])->timeout(5)->get("{$this->baseUrl}/api/v1/students/{$studentId}/invoices");

               if ($response->successful()) {
                   return $response->json();
               }

               Log::warning('Finance API error', [
                   'status' => $response->status(),
                   'body' => $response->body(),
               ]);
               return [];
           } catch (\Exception $e) {
               Log::error('Finance API exception', ['message' => $e->getMessage()]);
               return [];
           }
       }

       public function createInvoice(array $data): array
       {
           $response = Http::withHeaders([
               'Authorization' => request()->header('Authorization'),
               'Accept' => 'application/json',
           ])->post("{$this->baseUrl}/api/v1/invoices", $data);

           return $response->json();
       }
   }
   ```
2. Thêm vào `.env`:
   ```env
   FINANCE_SERVICE_URL=http://finance:8080
   ```
3. **Sử dụng trong Controller** (ví dụ `StudentController` ở School Domain):
   ```php
   use App\Domains\Finance\Services\FinanceApiClient;

   public function getInvoices(int $studentId, FinanceApiClient $financeClient)
   {
       $invoices = $financeClient->getInvoicesByStudent($studentId);
       return response()->json($invoices);
   }
   ```
**✅ Acceptance Criteria**: Gọi API `/api/school/students/1/invoices` từ Postman trả về dữ liệu từ Go Finance (hoặc `[]` nếu chưa có data).

---

### Bước 3.4: Fix lỗi DB Connection cho Finance Domain trong PHP (Bước B6)
**Nhiệm vụ**: Thêm connection `finance` vào `config/database.php` để tránh lỗi `InvalidArgumentException`.

**Hành động**:
1. Mở `config/database.php`.
2. Thêm vào mảng `connections`:
   ```php
   'finance' => [
       'driver' => 'mysql',
       'url' => env('DATABASE_URL'),
       'host' => env('DB_FINANCE_HOST', 'mysql'),
       'port' => env('DB_FINANCE_PORT', '3306'),
       'database' => env('DB_FINANCE_DATABASE', 'finance_db'),
       'username' => env('DB_FINANCE_USERNAME', 'educonnect'),
       'password' => env('DB_FINANCE_PASSWORD', ''),
       'unix_socket' => env('DB_SOCKET', ''),
       'charset' => 'utf8mb4',
       'collation' => 'utf8mb4_unicode_ci',
       'prefix' => '',
       'prefix_indexes' => true,
       'strict' => true,
       'engine' => null,
   ],
   ```
3. Thêm vào `.env`:
   ```env
   DB_FINANCE_HOST=mysql
   DB_FINANCE_DATABASE=finance_db
   DB_FINANCE_USERNAME=educonnect
   DB_FINANCE_PASSWORD=${DB_PASSWORD}
   ```
4. **Test**: Chạy `php artisan tinker` → `DB::connection('finance')->getPdo()` → Không được throw exception.
**✅ Acceptance Criteria**: `DB::connection('finance')->getPdo()` trả về PDO instance thành công.

---

## 🌐 Giai đoạn 4: API Gateway, Contract & Dọn dẹp Backend

### Bước 4.1: Verify Nginx Gateway Routing
**Nhiệm vụ**: Kiểm tra xem Nginx đã route đúng request đến Go Finance và PHP Laravel chưa.

**Hành động**:
1. Mở file `nginx/gateway.conf.template`.
2. **Kiểm tra các block `location`** đã có:
   - `/api/auth/*` → identity (PHP-FPM :9000)
   - `/api/finance/*` → finance (Go :8080)
   - `/api/*` (còn lại) → school (monolith Laravel :9000)
   - `/socket.io/*` → realtime (Node :3000)
   - `/health/*` → health checks upstream
3. **Test từ bên ngoài** (qua Cloudflare Tunnel hoặc localhost):
   ```bash
   curl -X GET "https://api.toanrobert.online/api/finance/health" \
        -H "Authorization: Bearer <your_jwt_token>"
   ```
4. **Test từ internal** (trong container nginx):
   ```bash
   docker exec <nginx_container> curl http://finance:8080/health
   ```
**✅ Acceptance Criteria**:
- Request `/api/finance/health` trả về `{"service":"finance-service","status":"healthy"}` từ Go.
- Request `/api/auth/login` trả về từ PHP Laravel.

---

### Bước 4.2: Tạo API Contract (Swagger/OpenAPI) cho Go Finance
**Nhiệm vụ**: Tạo tài liệu API để sau này Frontend dễ tích hợp (vì chưa có UI).

**Hành động**:
1. Trong `services/finance/`, tạo file `docs/swagger.yaml` (hoặc `docs/openapi.yaml`).
2. Viết OpenAPI spec cho các endpoint hiện có:
   ```yaml
   openapi: 3.0.0
   info:
     title: Finance API
     version: 1.0.0
   paths:
     /api/finance/fee-types:
       get:
         summary: Get all fee types
         responses:
           '200':
             description: List of fee types
             content:
               application/json:
                 schema:
                   type: array
                   items:
                     $ref: '#/components/schemas/FeeType'
     /api/finance/fee-types:
       post:
         summary: Create a new fee type
         requestBody:
           required: true
           content:
             application/json:
               schema:
                 $ref: '#/components/schemas/FeeTypeInput'
         responses:
           '201':
             description: Created fee type
   components:
     schemas:
       FeeType:
         type: object
         properties:
           id:
             type: integer
           name:
             type: string
           description:
             type: string
           amount:
             type: number
           isActive:
             type: boolean
       FeeTypeInput:
         type: object
         required: [name, amount]
         properties:
           name:
             type: string
           description:
             type: string
           amount:
             type: number
           isActive:
             type: boolean
   ```
3. (Tùy chọn) Expose endpoint `/api/v1/docs` trong Go Finance để serve Swagger UI.
**✅ Acceptance Criteria**: File `docs/swagger.yaml` tồn tại và mô tả đúng các endpoint của Go Finance.

---

### Bước 4.3: Dọn dẹp Legacy Code & Refactor Namespace (Bước B5, B8)
**Nhiệm vụ**: Dọn dẹp code cũ, đưa hết về cấu trúc Domain chuẩn.

**Hành động**:
1. **Xóa Dead Code** (Bước B8):
   - Xóa file `app/Domains/Identity/Http/Controllers/AuthController.php` (bị duplicate, chỉ giữ lại `Auth/AuthController.php`).
   - Xóa `routes/api.php.bak` (nếu có).
2. **Refactor Namespace** (Bước B5):
   - Dùng tool như **Rector** hoặc script `find + sed` để đổi:
     - `App\Models\User` → `App\Domains\Identity\Models\User`
     - `App\Models\Student` → `App\Domains\School\Models\Student`
     - `App\Services\AuthService` → `App\Domains\Identity\Services\AuthService`
   - **Ví dụ script bash**:
     ```bash
     find app -type f -name "*.php" -exec sed -i 's/App\\Models\\User/App\\Domains\\Identity\\Models\\User/g' {} +
     find app -type f -name "*.php" -exec sed -i 's/App\\Models\\Student/App\\Domains\\School\\Models\\Student/g' {} +
     ```
3. **Chạy test**:
   ```bash
   composer dump-autoload
   php artisan route:list
   ```
**✅ Acceptance Criteria**:
- Không còn file `AuthController.php` duplicate.
- `php artisan route:list` không báo lỗi class not found.
- Tất cả Model đã nằm trong `app/Domains/{Domain}/Models/`.

---

## 📋 Checklist tổng hợp cho AI Agent
- [ ] RabbitMQ UI: Exchange `user_events` bind đúng queue, Go consumers đang kết nối.
- [ ] Go Finance: Expose HTTP API `/api/v1/students/:id/invoices` và consume `finance_events`.
- [ ] PHP: `FinanceApiClient.php` đã tạo, gọi thành công sang Go Finance.
- [ ] PHP: `config/database.php` đã có connection `finance`, test `DB::connection('finance')` OK.
- [ ] Nginx: Route `/api/finance/*` proxy sang `finance:8080`, test từ external OK.
- [ ] Go Finance: Có file `docs/swagger.yaml` mô tả API.
- [ ] PHP: Xóa dead code `AuthController.php` duplicate, refactor namespace xong.
