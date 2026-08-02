# Service Layer & Dependency Injection

## Service Layer Architecture

Each domain uses a **Repository + Service** pattern:

```
Controller → Service (Interface) → Repository (Interface) → Eloquent Implementation
```

### Why This Pattern?
1. **Testability** — Services and repositories can be mocked via interfaces
2. **Decoupling** — Controllers don't directly access the database
3. **Domain isolation** — Each domain has its own service layer
4. **Business logic centralization** — Complex logic lives in services, not controllers

## Service Interfaces vs Implementations

### Interface Location
`app/Domains/{Domain}/Services/Interface/{ServiceName}Interface.php`

### Implementation Location
`app/Domains/{Domain}/Services/{ServiceName}.php`

### Example: AuthService
```
Interface: app/Domains/Identity/Services/Interface/AuthServiceInterface.php
Impl:      app/Domains/Identity/Services/AuthService.php
```

## DI Wiring (AppServiceProvider)

All service and repository bindings are registered in `app/Providers/AppServiceProvider.php`.

### Category A: Legacy (non-functional)
These bindings reference directories that **do not exist**:
- `App\Repositories\Contracts\*` → `App\Repositories\Eloquent\*`
- `App\Services\Interface\*` → `App\Services\*`
- `App\Services\*` (various legacy services)

⚠️ The directories `app/Services/` and `app/Repositories/` do not exist. These bindings will fail if resolved.

### Category B: Active Domain Bindings

#### School Domain
```php
// Repositories
ScheduleRepositoryInterface → ScheduleRepository
GradeRepositoryInterface → GradeRepository
DisciplineRepositoryInterface → DisciplineRepository
ConductScoreRepositoryInterface → ConductScoreRepository
DisciplineTypeRepositoryInterface → DisciplineTypeRepository

// Services
ScheduleServiceInterface → ScheduleService
GradeServiceInterface → GradeService
DisciplineServiceInterface → DisciplineService
ConductScoreServiceInterface → ConductScoreService
StudentServiceInterface → StudentService
DashBoardServiceInterface → DashBoardService
```

#### Identity Domain
```php
// Repositories
AuthRepositoryInterface → AuthRepository
EmailVerificationRepositoryInterface → EmailVerificationRepository
UserRepositoryInterface → UserRepository
RoleRepositoryInterface → RoleRepository
PermissionRepositoryInterface → PermissionRepository
RolePermissionRepositoryInterface → RolePermissionRepository

// Services
AuthServiceInterface → AuthService
RoleServiceInterface → RoleService
PermissionServiceInterface → PermissionService
UserRoleServiceInterface → UserRoleService
```

### Category C: Finance Domain
⚠️ **NO bindings exist** for Finance domain repositories or services. This domain has no HTTP layer and no DI wiring.

## Repository Pattern

Each repository interface defines the contract, and the Eloquent implementation provides the actual database queries.

### Identity Repositories
| Interface | Implementation | Key Methods |
|-----------|---------------|-------------|
| `AuthRepositoryInterface` | `AuthRepository` | `findByEmail()`, `create()`, `update()` |
| `UserRepositoryInterface` | `UserRepository` | `updateUser()`, `paginate()` |
| `RoleRepositoryInterface` | `RoleRepository` | `findByName()`, `create()`, `update()` |
| `PermissionRepositoryInterface` | `PermissionRepository` | `findByName()`, `create()`, `update()` |
| `RolePermissionRepositoryInterface` | `RolePermissionRepository` | `assignPermissions()`, `removePermission()` |
| `EmailVerificationRepositoryInterface` | `EmailVerificationRepository` | `upsert()`, `findByTokenHash()` |

### School Repositories
| Interface | Implementation | Key Methods |
|-----------|---------------|-------------|
| `ScheduleRepositoryInterface` | `ScheduleRepository` | `getByClass()`, `getWeeklySchedule()`, `mySchedule()` |
| `GradeRepositoryInterface` | `GradeRepository` | `getByStudentId()`, `getByClass()`, `getStatsForStudent()` |
| `DisciplineRepositoryInterface` | `DisciplineRepository` | `getByStudentUserId()`, `getByClassId()`, `getStatistics()` |
| `ConductScoreRepositoryInterface` | `ConductScoreRepository` | `getByClassId()`, `byStudent()` |
| `DisciplineTypeRepositoryInterface` | `DisciplineTypeRepository` | `findByName()` |

### Finance Repositories
| Interface | Implementation | Key Methods |
|-----------|---------------|-------------|
| `InvoiceRepositoryInterface` | `InvoiceRepository` | `getByStudentIds()`, `getAll()`, `getStatistics()` |
| `PaymentRepositoryInterface` | `PaymentRepository` | `getByInvoiceId()`, `getStatistics()` |
| `FeeTypeRepositoryInterface` | `FeeTypeRepository` | `getAll()`, `findByCode()` |

## Service Layer Business Logic Examples

### AuthService — Login Flow
The `AuthService::login()` method implements a multi-step security flow:

1. **Dual sliding window rate limit** — Redis Lua script with 2 keys (IP limit 30/min, IP+Email limit 10/min)
2. **Account lock check** — Redis key check for locked IP+Email
3. **Captcha requirement** — After 3 failed attempts, captcha required
4. **Find user + verify password** — Timing attack protection with dummy hash check
5. **Record failed attempt** — Atomic INCR + auto-lock after 5 attempts
6. **Reset attempts** — On successful login
7. **RBAC check** — Verify user has a valid role
8. **2FA check** — Return pre-auth token if TOTP/phone 2FA enabled
9. **Issue tokens** — Access token + rotating refresh token
10. **Audit log** — Async dispatch via queue job

### AuthService — Token Refresh with Theft Detection
```
1. Hash the raw refresh token
2. Look up RefreshToken record by token_hash
3. If revoked_at is set → THEFT DETECTED → revoke ALL sessions
4. If expired → throw error
5. Rotate: revoke old token, issue new pair
6. Store session in Redis + DB
```

### GradeService — Permission-Aware Access
```php
// Student: only own grades
// Parent: only children's grades
// Teacher/Admin: all grades
// Role-based checks in checkViewPermission()
```

### DisciplineService — Appeal Workflow
```
1. Teacher records discipline → status = 'pending'
2. Admin/Principal approves → status = 'confirmed'
3. Student/Parent can appeal → status = 'appealed'
4. Admin/Principal approves appeal → discipline rejected, penalty = 0
5. Admin/Principal rejects appeal → discipline confirmed
```

### InvoiceService — Multi-Role Access
```php
// Admin/Principal/Accountant: view all
// Teacher: view only class students' invoices
// Student: view only own invoices
// Parent: view only children's invoices
```

## Middleware Layer

### Custom Middleware
| Middleware | Location | Purpose |
|-----------|----------|---------|
| `JwtMiddleware` | `app/Domains/Identity/Middleware/JwtMiddleware.php` | JWT token validation |
| `JsonRoleMiddleware` | `app/Http/Middleware/JsonRoleMiddleware.php` | Role check returning JSON |
| `ValidateApiAccess` | `app/Http/Middleware/ValidateApiAccess.php` | API access validation |
| `IdempotencyMiddleware` | `app/Http/Middleware/IdempotencyMiddleware.php` | Idempotency key checking |
| `RateLimitMiddleware` | `app/Http/Middleware/RateLimitMiddleware.php` | Rate limiting |
| `RateLimitRegister` | `app/Http/Middleware/RateLimitRegister.php` | Registration rate limiting |
| `RoleBasedRedirect` | `app/Http/Middleware/RoleBasedRedirect.php` | Role-based redirect |
| `SecureHeadersMiddleware` | `app/Http/Middleware/SecureHeadersMiddleware.php` | Security headers |
| `DecryptCookiesForRefresh` | `app/Http/Middleware/DecryptCookiesForRefresh.php` | Cookie decryption for refresh |
| `HandleInertiaRequest` | `app/Http/Middleware/HandleInertiaRequest.php` | Inertia request handling |

### Spatie Middleware (via aliases)
| Alias | Class | Purpose |
|-------|-------|---------|
| `role` | `JsonRoleMiddleware` | Role check |
| `permission` | `Spatie\Permission\Middleware\PermissionMiddleware` | Permission check |
| `role_or_permission` | `Spatie\Permission\Middleware\RoleOrPermissionMiddleware` | Either role or permission |

## Queue Jobs

| Job | Purpose | Queue |
|-----|---------|-------|
| `WriteAuditLog` | Async audit log writing | `audit` |
| `SendVerificationEmail` | Send email verification | `emails` |
| `SendPasswordResetEmail` | Send password reset email | `emails` |

## Events

| Event | Purpose |
|-------|---------|
| `UserRegistered` | Fired after user registration, used for notification dispatch |

## Key Design Decisions

1. **No direct model imports across domains** — Identity doesn't import School/Finance models; cross-domain queries use `user_id`
2. **Redis for caching and rate limiting** — All auth-related rate limiting uses Redis Lua scripts
3. **Refresh token rotation** — Every refresh invalidates the old token and issues a new pair
4. **Theft detection** — Reuse of a revoked refresh token triggers mass session revocation
5. **Async audit logging** — Audit logs are dispatched as queue jobs with DB fallback
6. **Transaction wrapping** — All write operations use DB transactions with rollback on failure