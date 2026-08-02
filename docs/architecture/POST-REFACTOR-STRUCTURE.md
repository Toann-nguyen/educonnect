# Cấu trúc thư mục sau khi tách Microservices

> Tài liệu này mô tả cấu trúc thư mục cuối cùng sau khi hoàn thành quá trình tách monolith `educonnect` thành **modular monolith + Go microservices**, dựa trên kế hoạch trong `docs/microservices/`.

---

## 📁 Cấu trúc thư mục gốc (Root)

```
~/educonnect/
├── app/
│   ├── Console/              # Artisan commands
│   ├── Domains/              # DDD bounded contexts (PHP Laravel monolith)
│   ├── Enums/                # Global enums (RoleEnum, PermissionEnum)
│   ├── Exceptions/           # Global exception handlers
│   ├── Helpers/              # Shared helper functions
│   ├── Http/                  # Legacy/global HTTP layer (còn dư cho đến khi migration xong)
│   ├── Jobs/                  # Legacy/global jobs (sẽ migrate vào Domains)
│   ├── Mail/                  # Legacy/global mailables
│   ├── Observers/             # Model observers
│   ├── Providers/             # Service providers (AppServiceProvider, RouteServiceProvider)
│   ├── Support/               # Shared support classes
│   ├── Traits/                # Shared traits
│   └── (NO Models/ or Services/ or Repositories/ ở cấp này — đã chuyển vào Domains)
├── bootstrap/                 # Framework bootstrap
├── build/                     # Frontend build artifacts
├── config/                    # Laravel config files
├── database/
│   ├── migrations/
│   │   ├── identity/          # Identity domain migrations (users, roles, permissions, tokens...)
│   │   ├── school/            # School domain migrations (students, classes, grades...)
│   │   └── (legacy root)      # Các migration chung (sẽ được dọn vào identity/school)
│   ├── seeders/               # Seed classes
│   └── factories/             # Model factories
├── docker/                    # Docker configs for development
├── docs/
│   ├── architecture/          # Kiến trúc chi tiết
│   ├── microservices/         # Kế hoạch tách microservices
│   ├── tests/                 # Test plans
│   └── *.md                   # Các file docs riêng lẻ (auth-token-flow, login, register...)
├── nginx/                     # Nginx configs (production)
├── production/                # Thư mục hạ tầng production (deploy riêng)
├── resources/                 # Frontend assets (Vue 3 + Inertia.js)
│   ├── js/
│   │   ├── Pages/             # Inertia pages
│   │   ├── Components/        # Vue components
│   │   └── ...
│   └── css/
├── routes/
│   ├── api.php                # Health check only
│   ├── web.php                # Root endpoint
│   ├── identity.php           # Identity domain routes (/api/auth/*, /api/admin/roles...)
│   ├── school.php             # School domain routes (/api/class, /api/schedule...)
│   └── channels.php            # Broadcasting channels
├── services/                  # Go microservices (tách biệt PHP monolith)
│   ├── finance/               # Go service quản lý tài chính
│   │   ├── main.go
│   │   ├── go.mod
│   │   ├── go.sum
│   │   └── Dockerfile
│   └── notify/                # Go service gửi thông báo
│       ├── main.go
│       ├── go.mod
│       ├── go.sum
│       └── Dockerfile
├── public/                    # Web root
├── scripts/                   # Deploy scripts
├── storage/                   # Laravel storage
├── tests/                     # PHPUnit tests
├── vendor/                    # PHP dependencies
├── node_modules/              # JS dependencies
├── composer.json
├── package.json
├── Dockerfile
├── docker-compose.yml         # Development compose
├── docker-compose.prod.yml    # Production compose
├── deploy.sh                  # Deploy script
├── phpunit.xml
├── vite.config.js
├── tailwind.config.js
├── postcss.config.js
├── TECH-STACK.md              # Tech stack chính thức (cập nhật: 2026-06-16)
├── README.md
├── README-API.md
├── README-Logic.md
├── README-setup.md
├── README-ERD.md
├── README-URL.md
├── README-extend.md
├── update_logic.md            # Kế hoạch production deploy (Cloudflare + React)
├── composer.lock
├── package-lock.json
└── .env.*                     # Environment configs
```

---

## 🏗️ Cấu trúc `app/Domains/` (DDD)

Mỗi domain tuân theo pattern **Layered Architecture**:

```
app/Domains/{Domain}/
├── Models/                     # Eloquent models
├── Http/
│   ├── Controllers/            # HTTP controllers
│   ├── Requests/               # Form request validation
│   └── Resources/              # API resource transformers
├── Repositories/
│   ├── Contracts/              # Repository interface
│   └── Eloquent/               # Eloquent implementation
├── Services/
│   ├── Interface/              # Service interface
│   └── *.php                   # Service implementations
├── Policies/                   # Authorization policies
├── Middleware/                 # Domain-specific middleware
├── Jobs/                       # Queue jobs
├── Mail/                       # Mailable classes
├── Events/                     # Domain events
├── Exceptions/                 # Domain-specific exceptions
└── Support/                    # Domain helpers
```

### Cấu trúc chi tiết từng Domain

#### 1. Identity Domain (`app/Domains/Identity/`) — ✅ Hoạt động

```
app/Domains/Identity/
├── Models/
│   ├── User.php               # Auth user, JWT, 2FA
│   ├── Profile.php
│   ├── Role.php
│   ├── Permission.php
│   ├── RefreshToken.php
│   ├── UserSession.php
│   ├── EmailVerification.php
│   ├── PasswordResetToken.php
│   ├── BackupCode.php
│   └── AuditLog.php
├── Http/
│   ├── Controllers/
│   │   ├── Controller.php
│   │   ├── AuthController.php          # ⚠️ Duplicate (dead code)
│   │   ├── PermissionController.php
│   │   ├── RoleController.php
│   │   ├── UserRoleController.php
│   │   └── Auth/
│   │       ├── AuthController.php       # Active (routes point here)
│   │       ├── EmailController.php
│   │       ├── PasswordController.php
│   │       ├── SessionController.php
│   │       └── TwoFactorController.php
│   ├── Requests/
│   │   ├── AssignPermissionsRequest.php
│   │   ├── StorePermissionRequest.php
│   │   ├── StoreRoleRequest.php
│   │   └── UpdateRoleRequest.php
│   └── Resources/
│       ├── LoginResource.php
│       ├── PermissionResource.php
│       ├── ProfileResource.php
│       ├── RoleResource.php
│       └── UserResource.php
├── Repositories/
│   ├── Contracts/
│   │   ├── AuthRepositoryInterface.php
│   │   ├── UserRepositoryInterface.php
│   │   ├── RoleRepositoryInterface.php
│   │   ├── PermissionRepositoryInterface.php
│   │   ├── RolePermissionRepositoryInterface.php
│   │   └── EmailVerificationRepositoryInterface.php
│   ├── Eloquent/
│   │   ├── UserRepository.php
│   │   ├── RoleRepository.php
│   │   ├── PermissionRepository.php
│   │   ├── RolePermissionRepository.php
│   │   └── UserRepository.php
│   └── Auth/  # Subfolder
│       ├── AuthRepository.php
│       └── EmailVerificationRepository.php
├── Services/
│   ├── Interface/
│   │   ├── AuthServiceInterface.php
│   │   ├── RoleServiceInterface.php
│   │   ├── PermissionServiceInterface.php
│   │   └── UserRoleServiceInterface.php
│   ├── AuthService.php          # Multi-layer security (rate limit, lock, 2FA, JWT)
│   ├── RegisterService.php      # Registration flow
│   ├── RoleService.php
│   ├── PermissionService.php
│   ├── UserRoleService.php
│   └── PermissionCacheService.php
├── Policies/
│   └── ProfilePolicy.php
├── Middleware/
│   └── JwtMiddleware.php
├── Jobs/
│   ├── SendVerificationEmail.php
│   └── WriteAuditLog.php
├── Events/
│   └── UserRegistered.php
├── Mail/
│   └── VerifyEmailMail.php
├── Exceptions/
│   └── Auth/
│       ├── AccountLockedException.php
│       ├── CaptchaRequiredException.php
│       ├── InvalidCredentialsException.php
│       └── IPSpamException.php
└── Support/
    └── RequestIp.php
```

#### 2. School Domain (`app/Domains/School/`) — ⚠️ Một số controller chưa hoàn thiện

```
app/Domains/School/
├── Models/
│   ├── Student.php
│   ├── SchoolClass.php
│   ├── Subject.php
│   ├── Schedule.php
│   ├── Grade.php
│   ├── Attendance.php
│   ├── Discipline.php
│   ├── DisciplineType.php
│   ├── DisciplineAction.php
│   ├── DisciplineAppeal.php
│   ├── StudentConductScore.php
│   ├── AcademicYear.php
│   ├── Event.php
│   ├── EventRegistration.php
│   ├── LibraryBook.php
│   ├── LibraryTransaction.php
│   └── StudentGuardian.php
├── Http/
│   ├── Controllers/
│   │   ├── Controller.php
│   │   ├── AcademicYearController.php
│   │   ├── AttendanceController.php
│   │   ├── ConductScoreController.php
│   │   ├── DashBoardController.php
│   │   ├── DisciplineController.php
│   │   ├── DisciplineTypeController.php
│   │   ├── EventController.php
│   │   ├── EventRegistrationController.php
│   │   ├── GradeController.php
│   │   ├── LibraryBookController.php
│   │   ├── LibraryTransactionController.php
│   │   ├── ScheduleController.php
│   │   ├── SchoolClassController.php
│   │   ├── StudentController.php     # ⚠️ Skeleton (methods empty)
│   │   ├── StudentGuardianController.php
│   │   └── SubjectController.php
│   └── Resources/ (legacy — chưa migrate vào domain)
├── Repositories/
│   ├── Contracts/
│   │   ├── ScheduleRepositoryInterface.php
│   │   ├── GradeRepositoryInterface.php
│   │   ├── DisciplineRepositoryInterface.php
│   │   ├── ConductScoreRepositoryInterface.php
│   │   └── DisciplineTypeRepositoryInterface.php
│   └── Eloquent/
│       ├── ScheduleRepository.php
│       ├── GradeRepository.php
│       ├── DisciplineRepository.php
│       ├── ConductScoreRepository.php
│       └── DisciplineTypeRepository.php
├── Services/
│   ├── Interface/
│   │   ├── ScheduleServiceInterface.php
│   │   ├── GradeServiceInterface.php
│   │   ├── DisciplineServiceInterface.php
│   │   ├── ConductScoreServiceInterface.php
│   │   └── DashBoardServiceInterface.php
│   ├── ScheduleService.php
│   ├── GradeService.php
│   ├── DisciplineService.php
│   ├── ConductScoreService.php
│   ├── DashBoardService.php
│   ├── StudentService.php          # ⚠️ Interface tồn tại nhưng chưa có implementation?
│   └── ActivityLogService.php
├── Policies/
│   ├── AcademicYearPolicy.php
│   ├── AttendancePolicy.php
│   ├── DisciplinePolicy.php
│   ├── EventPolicy.php
│   ├── EventRegistrationPolicy.php
│   ├── GradePolicy.php
│   ├── LibraryBookPolicy.php
│   ├── LibraryTransactionPolicy.php
│   ├── SchedulePolicy.php
│   ├── SchoolClassPolicy.php
│   ├── StudentGuardianPolicy.php
│   ├── StudentPolicy.php
│   └── SubjectPolicy.php
├── Jobs/
├── Mail/
├── Events/
├── Exceptions/
└── Middleware/
```

#### 3. Finance Domain (`app/Domains/Finance/`) — ❌ Chưa có HTTP layer

```
app/Domains/Finance/
├── Models/
│   ├── Invoice.php
│   ├── InvoiceItem.php
│   ├── Payment.php
│   └── FeeType.php
├── Repositories/
│   ├── Contracts/
│   │   ├── InvoiceRepositoryInterface.php
│   │   ├── PaymentRepositoryInterface.php
│   │   └── FeeTypeRepositoryInterface.php
│   └── Eloquent/
│       ├── InvoiceRepository.php
│       ├── PaymentRepository.php
│       └── FeeTypeRepository.php
├── Services/
│   ├── Interface/
│   │   ├── InvoiceServiceInterface.php
│   │   ├── PaymentServiceInterface.php
│   │   └── FeeTypeServiceInterface.php
│   ├── InvoiceService.php
│   ├── PaymentService.php
│   └── FeeTypeService.php
├── Policies/
│   ├── InvoicePolicy.php
│   ├── PaymentPolicy.php
│   └── FeeTypePolicy.php
├── Http/
│   └── Controllers/        # ⚠️ THƯ MỤC RỖNG (chưa có controllers)
├── Jobs/
├── Mail/
├── Events/
├── Exceptions/
└── Middleware/
```

---

## 🐹 Go Services (`services/`)

```
services/
├── finance/                 # Go microservice cho Finance domain
│   ├── main.go              # Entry point, HTTP handlers
│   ├── go.mod               # Go module: github.com/educonnect/finance
│   ├── go.sum
│   └── Dockerfile
└── notify/                  # Go microservice gửi thông báo
    ├── main.go              # Consumer Redis Stream → gửi email/SMS
    ├── go.mod               # Go module: github.com/educonnect/notify
    ├── go.sum
    └── Dockerfile
```

### Giao tiếp giữa các service

```
                    ┌─────────────────┐
                    │   Next.js       │
                    │   Frontend      │
                    │ (Cloudflare     │
                    │  Pages)         │
                    └────────┬────────┘
                             │ HTTPS
                             ▼
                    ┌─────────────────┐
                    │  Nginx Gateway  │
                    │  (Cloudflare    │
                    │   Tunnel)       │
                    └──┬───────┬──────┘
                       │       │
         /api/auth/*   │       │  /api/finance/*
                       ▼       ▼
              ┌──────────┐  ┌──────────┐
              │ PHP      │  │ Go       │
              │ Identity │  │ Finance  │
              │ API      │  │ Service  │
              └──────────┘  └────┬─────┘
                                 │
                         /api/notify/*
                                 ▼
                            ┌──────────┐
                            │ Go       │
                            │ Notify   │
                            │ Service  │
                            └────┬─────┘
                                 │
                          ┌──────┴──────┐
                          │  Redis      │
                          │  Stream     │
                          │ (event bus) │
                          └─────────────┘
```

---

## 🗄️ Database per-Service

| Service | Database | Connection | Migrations |
|---------|----------|------------|------------|
| Identity | MySQL | `identity` | `database/migrations/identity/` |
| School | MySQL | `school` | `database/migrations/school/` |
| Finance | MySQL/Postgres | `finance` | `database/migrations/finance/` |
| Notify | Redis (not DB) | - | - |

> ⚠️ **Lưu ý**: Connection `finance` chưa được định nghĩa trong `config/database.php`. Cần thêm config này để tránh `InvalidArgumentException` khi Finance domain được sử dụng.

---

## 🔌 Cấu hình Infrastructure

### Docker Compose (Production)

```
~/production/
├── nginx/
│   └── conf.d/
│       ├── api.toanrobert.online.conf    # PHP Laravel + Go proxy routing
│       └── rate-limit.conf               # Nginx rate limit zones
└── docker/
    ├── .env                              # Environment variables
    └── docker-compose.yml                # 6 containers:
        #   1. app (PHP-FPM Laravel)
        #   2. nginx (reverse proxy)
        #   3. mysql (MySQL 8.0)
        #   4. redis (Redis 7)
        #   5. horizon (queue worker)
        #   6. scheduler (cron)
        #   + cloudflared (tunnel daemon)
```

### Environment Variables (`.env.production`)

```env
# App
APP_ENV=production
APP_URL=https://api.toanrobert.online

# Database (per-service)
DB_IDENTITY_CONNECTION=identity
DB_IDENTITY_HOST=mysql
DB_IDENTITY_DATABASE=identity_db
DB_IDENTITY_USERNAME=educonnect

DB_SCHOOL_CONNECTION=school
DB_SCHOOL_HOST=mysql
DB_SCHOOL_DATABASE=school_db
DB_SCHOOL_USERNAME=educonnect

DB_FINANCE_CONNECTION=finance   # Chưa được thêm vào config!
DB_FINANCE_DATABASE=finance_db

# Redis
REDIS_HOST=redis
REDIS_PASSWORD=***

# JWT
JWT_SECRET=***

# Cloudflare
CLOUDFLARE_TUNNEL_TOKEN=***
```

---

## 🔄 Migration Plan — Trạng thái hiện tại

| Bước | Task | Branch | Trạng thái |
|------|------|--------|------------|
| B1 | Merge Identity Service | `~/educonnect-identity/` → `app/Domains/Identity/` | ✅ ĐÃ HOÀN THIỆN ở repo riêng, chờ merge |
| B2 | Split School Domain | Tách từ monolith → `app/Domains/School/` | ✅ ĐÃ KHẢO SÁT (14 models, 14 controllers) |
| B3 | Finance (Go) | Tách `services/finance/` | ✅ ĐANG CODE |
| B4 | Notify (Go) | Tách `services/notify/` | ✅ ĐANG CODE |
| B5 | Refactor namespace | `App\Models\User` → `App\Domains\Identity\Models\User` | ⚠️ CẦN LÀM |
| B6 | Thêm `finance` DB connection | `config/database.php` | ❌ CHƯA LÀM |
| B7 | Finance HTTP layer | Controllers, routes, requests cho Finance | ❌ CHƯA LÀM |
| B8 | Clean legacy files | Xóa `routes/api.php.bak`, duplicate middleware, etc. | ⚠️ CẦN LÀM |

---

## 📡 API Gateway Routing (Nginx Ingress)

| Path | Service | Tech |
|------|---------|------|
| `/api/auth/*` | Identity | PHP Laravel |
| `/api/school/*` | School | PHP Laravel |
| `/api/finance/*` | Finance | Go |
| `/api/notify/*` | Notify | Go |
| `/` (root) | Frontend | Next.js (Cloudflare Pages) |

---

## 📊 Tổng kết Domain Status

| Domain | Models | Controllers | Routes | Services | Repositories | DB | Status |
|--------|--------|-------------|--------|----------|-------------|-----|--------|
| **Identity** | 10 | 7 (1 duplicate) | ✅ `routes/identity.php` | 6 | 6 | `identity` | ✅ Hoạt động |
| **School** | 18 | 17 (1 skeleton) | ✅ `routes/school.php` | 8 | 5 | `school` | ⚠️ Cần fix |
| **Finance** | 4 | 0 | ❌ Chưa có | 3 | 3 | `finance` (chưa config) | ❌ Chưa hoàn thiện |
