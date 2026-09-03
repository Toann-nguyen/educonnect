# Domain Architecture

## Overview

The application follows **Domain-Driven Design (DDD)** with three bounded contexts:

```
┌─────────────────────────────────────────────────────────────────┐
│                        Application Layer                        │
│  (HTTP Kernel, Middleware, Routes, Controllers, Requests,      │
│   Resources, Jobs, Mail, Events, Exceptions, Traits, Helpers) │
└─────────────────────────────────────────────────────────────────┘
                              │
          ┌───────────────────┼───────────────────┐
          ▼                   ▼                   ▼
┌─────────────────┐ ┌─────────────────┐ ┌─────────────────┐
│  Identity Domain │ │  School Domain  │ │ Finance Domain  │
│  (Auth, Users,   │ │ (Students,      │ │ (Invoices,      │
│   Roles, Perms,  │ │  Classes,       │ │  Payments,      │
│   2FA, Sessions, │ │  Grades,        │ │  Fee Types)     │
│   Audit Logs)    │ │  Schedules,     │ │                 │
│                  │ │  Attendance,    │ │                 │
│                  │ │  Discipline,    │ │                 │
│                  │ │  Library,       │ │                 │
│                  │ │  Events)        │ │                 │
└─────────────────┘ └─────────────────┘ └─────────────────┘
```

## Domain Structure Pattern

Each domain follows a consistent layered architecture:

```
Domains/{DomainName}/
├── Models/              # Eloquent models
├── Http/
│   ├── Controllers/     # HTTP controllers
│   ├── Requests/        # Form request validation classes
│   └── Resources/       # API resource transformers
├── Repositories/
│   ├── Contracts/       # Repository interface definitions
│   └── Eloquent/        # Eloquent repository implementations
├── Services/
│   ├── Interface/       # Service interface definitions
│   └── ...              # Service implementations
├── Policies/            # Authorization policies
├── Middleware/          # Domain-specific middleware
├── Jobs/                # Queue jobs
├── Mail/                # Mailable classes
├── Events/              # Domain events
├── Exceptions/          # Domain-specific exceptions
└── Support/             # Domain-specific helpers
```

## Identity Domain

**Purpose**: Authentication, user management, role-based access control, 2FA, audit logging.

### Models
| Model | Table | Connection | Key Fields |
|-------|-------|-----------|------------|
| User | users | identity | name, email, password_hash, phone, totp_secret, totp_enabled, status, is_active, is_locked |
| Profile | profiles | identity | user_id, full_name, phone_number, birthday, gender, address |
| Role | roles | identity | name, description, is_active |
| Permission | permissions | identity | name, description, guard_name |
| RefreshToken | refresh_tokens | identity | user_id, token_hash, device_info, device_fingerprint, expires_at, revoked_at |
| UserSession | user_sessions | identity | user_id, refresh_token_id, device_name, ip_address, user_agent |
| EmailVerification | email_verifications | identity | user_id, token_hash, expires_at |
| PasswordResetToken | password_reset_tokens | identity | user_id, token_hash, expires_at |
| BackupCode | backup_codes | identity | user_id, code_hash, is_used |
| AuditLog | audit_logs | identity | user_id, action, ip_address, user_agent, metadata |

### Controllers
| Controller | Purpose |
|-----------|---------|
| Auth\AuthController | Login, logout, register, forgot/reset password, email verification, refresh token |
| Auth\EmailController | Resend email verification |
| Auth\PasswordController | Change password |
| Auth\TwoFactorController | TOTP setup/enable/disable, backup codes |
| Auth\SessionController | List/destroy sessions |
| RoleController | CRUD roles, assign permissions |
| PermissionController | CRUD permissions |
| UserRoleController | Assign/revoke roles & permissions to users |

### Services
| Service | Purpose |
|---------|---------|
| AuthService | Login flow, logout, token refresh, rate limiting, 2FA |
| RegisterService | User registration with email verification |
| RoleService | Role CRUD, permission assignment |
| PermissionService | Permission CRUD, role-permission mapping |
| PermissionCacheService | Cache permission lookups in Redis |
| UserRoleService | User-role assignment management |

## School Domain

**Purpose**: Student management, classes, grades, schedules, attendance, discipline, library, events.

### Models
| Model | Table | Connection | Key Fields |
|-------|-------|-----------|------------|
| Student | students | school | user_id, class_id, student_code, status |
| SchoolClass | classes | school | name, academic_year_id, homeroom_teacher_id |
| Subject | subjects | school | name, subject_code |
| Schedule | schedules | school | class_id, subject_id, teacher_id, day_of_week, period, room |
| Grade | grades | school | student_id, subject_id, teacher_id, score, type, semester |
| Attendance | attendances | school | student_id, schedule_id, date, status, note |
| Discipline | disciplines | school | student_id, discipline_type_id, incident_date, description, status, penalty_points |
| DisciplineType | discipline_types | school | name, default_penalty_points |
| DisciplineAction | discipline_actions | school | discipline_id, action_type, note |
| DisciplineAppeal | discipline_appeals | school | discipline_id, appellant_user_id, appellant_type, appeal_reason, status |
| StudentConductScore | student_conduct_scores | school | student_id, academic_year_id, semester, total_penalty_points |
| AcademicYear | academic_years | school | name, start_date, end_date, is_active |
| Event | events | school | title, description, start_date, end_date, location |
| EventRegistration | event_registrations | school | event_id, student_id, status |
| LibraryBook | library_books | school | title, author, isbn, quantity |
| LibraryTransaction | library_transactions | school | book_id, student_id, borrowed_at, returned_at |
| StudentGuardian | student_guardians | school | student_id, guardian_user_id, relationship |

### Controllers
| Controller | Purpose |
|-----------|---------|
| AcademicYearController | CRUD academic years (admin only) |
| SchoolClassController | CRUD classes (admin only) |
| SubjectController | CRUD subjects (admin only) |
| ScheduleController | Manage schedules (admin/principal/teacher) |
| GradeController | Manage grades (admin/principal/teacher) |
| StudentController | Student management (parent views children) |
| AttendanceController | Attendance tracking |
| DisciplineController | Discipline records, appeals, statistics |
| DisciplineTypeController | CRUD discipline types (admin/principal) |
| ConductScoreController | Conduct score management |
| DashBoardController | Dashboard statistics |
| EventController | Event CRUD |
| EventRegistrationController | Event registration |
| LibraryBookController | Library book management |
| LibraryTransactionController | Library borrowing/returning |
| StudentGuardianController | Student-guardian linkage |

### Services
| Service | Purpose |
|---------|---------|
| StudentService | Student CRUD, parent children lookup |
| GradeService | Grade CRUD, statistics, permission checks |
| DisciplineService | Discipline CRUD, approval, appeals, statistics |
| ScheduleService | Schedule CRUD, class/teacher queries |
| ConductScoreService | Conduct score CRUD, recalculation |
| DashBoardService | Dashboard data aggregation |
| ActivityLogService | Activity logging |

## Finance Domain

**Purpose**: Invoice management, payment processing, fee types.

### Models
| Model | Table | Connection | Key Fields |
|-------|-------|-----------|------------|
| Invoice | invoices | finance | invoice_number, student_id, issued_by, total_amount, paid_amount, due_date, status |
| Payment | payments | finance | invoice_id, payer_user_id, created_by_user_id, amount_paid, payment_date, payment_method |
| FeeType | fee_types | finance | code, name, default_amount, description, is_active |
| InvoiceItem | invoice_items | finance | invoice_id, fee_type_id, amount |

### Controllers
⚠️ **No HTTP layer yet** — no controllers, routes, or request classes exist for Finance.

### Services
| Service | Purpose |
|---------|---------|
| InvoiceService | Invoice CRUD, role-based access, statistics |
| PaymentService | Payment creation, invoice status sync, statistics |
| FeeTypeService | Fee type CRUD |

## Cross-Domain Relationships

```
Identity.User ──(user_id)──▶ School.Student
Identity.User ──(user_id)──▶ School.StudentGuardian.guardian_user_id
Identity.User ──(user_id)──▶ Finance.Invoice.issued_by
Identity.User ──(user_id)──▶ Finance.Payment.payer_user_id
Identity.User ──(user_id)──▶ Finance.Payment.created_by_user_id
School.Student ──(student_id)──▶ Finance.Invoice.student_id
School.Student ──(student_id)──▶ Finance.Payment (via invoice)
```

**Key design decision**: Identity domain does NOT import School or Finance models. Cross-domain queries use `user_id` as the foreign key and are resolved through each domain's own service layer.