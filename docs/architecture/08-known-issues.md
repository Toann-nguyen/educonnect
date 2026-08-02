# Known Issues & Technical Debt

## Critical Issues 🔴

### 1. Missing `finance` Database Connection
- **File**: `config/database.php`
- **Impact**: All Finance models (`Invoice`, `Payment`, `FeeType`, `InvoiceItem`) declare `protected $connection = 'finance'` but this connection is not defined in the database config. This will cause `InvalidArgumentException` at runtime.
- **Fix**: Add the `finance` connection configuration to `config/database.php`.

### 2. Missing Legacy Directories Referenced in DI Container
- **Directories**: `app/Services/` and `app/Repositories/` do not exist
- **Impact**: `AppServiceProvider` binds interfaces from `App\Repositories\Contracts\*` and `App\Services\Interface\*` to implementations in non-existent directories. Any resolution of these bindings will fail.
- **Affected bindings**: `AuthRepositoryInterface`, `UserRepositoryInterface`, `ConductScoreRepositoryInterface`, `DisciplineRepositoryInterface`, `DisciplineTypeRepositoryInterface`, `FeeTypeRepositoryInterface`, `GradeRepositoryInterface`, `InvoiceRepositoryInterface`, `PaymentRepositoryInterface`, `PermissionRepositoryInterface`, `RolePermissionRepositoryInterface`, `RoleRepositoryInterface`, `ScheduleRepositoryInterface`, `AuthServiceInterface`, `AuthServicesInterface`, `DashBoardServiceInterface`, `DisciplineServiceInterface`, `FeeTypeServiceInterface`, `GradeServiceInterface`, `InvoiceServiceInterface`, `PaymentServiceInterface`, `PermissionServiceInterface`, `ScheduleServiceInterface`, `StudentServiceInterface`, `UserServiceInterface`, `UserRoleServiceInterface`, `RoleServiceInterface`, `ConductScoreServiceInterface`
- **Note**: Some of these legacy bindings are shadowed by the active Domain bindings in AppServiceProvider, so they may not all be resolved in practice.

### 3. Finance Domain Has No HTTP Layer
- **Impact**: Finance domain has models, repositories, and services but no controllers, routes, or request classes. The domain is completely inaccessible via HTTP.
- **Affected**: `InvoiceController`, `PaymentController`, `FeeTypeController` — all missing.

### 4. AuthServiceProvider Has No Policy Mappings
- **File**: `app/Providers/AuthServiceProvider.php`
- **Impact**: The `$policies` array is empty despite 12 policy files existing in the codebase. No authorization policies are registered.
- **Affected policies**: `SubjectPolicy`, `EventPolicy`, `AcademicYearPolicy`, `EventRegistrationPolicy`, `LibraryBookPolicy`, `SchedulePolicy`, `AttendancePolicy`, `LibraryTransactionPolicy`, `DisciplinePolicy`, `SchoolClassPolicy`, `StudentPolicy`, `GradePolicy`, `StudentGuardianPolicy`, `FeeTypePolicy`, `InvoicePolicy`, `PaymentPolicy`, `ProfilePolicy`

## High Priority Issues 🟠

### 5. Duplicate JwtMiddleware
- **Files**:
  - `app/Domains/Identity/Middleware/JwtMiddleware.php` (active, used in routes)
  - `app/Http/Middleware/JwtMiddleware.php` (legacy, dead code)
- **Impact**: The legacy file is never referenced in routes or middleware aliases but still exists in the codebase.

### 6. StudentController Is a Skeleton
- **File**: `app/Domains/School/Http/Controllers/StudentController.php`
- **Impact**: All methods are empty stubs with no implementation.

### 7. Incomplete Migration History
- **File**: `routes/api.php.bak`
- **Impact**: Contains ~200+ lines of old routes (QR codes, OAuth, legacy endpoints) that haven't been migrated to the domain structure. This file should be removed or fully migrated.

### 8. Hardcoded Captcha Token
- **File**: `app/Domains/Identity/Services/AuthService.php:247`
- **Code**: `$captchaToken !== 'valid_captcha_token'`
- **Impact**: The captcha validation uses a hardcoded string literal instead of actual captcha validation. This is a security vulnerability.

### 9. Permission Cache TTL
- **File**: `app/Domains/Identity/Services/PermissionCacheService.php`
- **Impact**: Permission cache TTL of 300 seconds may cause stale permissions after role/permission changes.

## Medium Priority Issues 🟡

### 10. Mixed Naming Conventions
- `AuthServices` (plural) in `app/Services/AuthServices.php` vs `AuthService` (singular) in `app/Domains/Identity/Services/AuthService.php`
- Inconsistent naming between legacy and domain-based code.

### 11. Unused HasRolePermission Trait
- **File**: `app/Traits/HasRolePermission.php`
- **Impact**: The trait is defined but not applied to any model.

### 12. Duplicate Dependency in composer.json
- **Package**: `knuckleswtf/scribe` appears in both `require` and `require-dev`.

### 13. Duplicate Controller Files
- **Files**:
  - `app/Domains/Identity/Http/Controllers/AuthController.php` (empty, 11 lines)
  - `app/Domains/Identity/Http/Controllers/Auth/AuthController.php` (empty, 11 lines)
- **Impact**: The routes use `Auth\AuthController` (the subdirectory version). The root-level `AuthController.php` is dead code.

### 14. Hardcoded Redis Lua Scripts
- **File**: `app/Domains/Identity/Services/AuthService.php`
- **Impact**: Rate limiting and attempt tracking use inline Lua scripts embedded in PHP strings. This makes them hard to test, maintain, and modify independently.

### 15. Missing Finance Repository Bindings
- **Impact**: Finance repository interfaces (`InvoiceRepositoryInterface`, `PaymentRepositoryInterface`, `FeeTypeRepositoryInterface`) are not bound in `AppServiceProvider`, even though the Finance domain has Eloquent implementations.

## Low Priority Issues 🟢

### 16. Inconsistent Soft Delete Usage
- Some models use `SoftDeletes` (e.g., `Schedule`, `Invoice`, `Payment`, `FeeType`), others don't (e.g., `Student`, `Grade`, `Attendance`).

### 17. Missing API Resource for Some Endpoints
- Some endpoints return raw model data or arrays instead of using API Resource classes for consistent response formatting.

### 18. Unused Imports in AppServiceProvider
- `SebastianBergmann\CodeCoverage\Report\Html\Dashboard` is imported but never used.

## Architecture Decisions

### Why 3 Database Connections?
The application separates data by domain for isolation:
- **Identity**: User credentials, auth tokens, sessions — security-sensitive
- **School**: Academic data — high volume, frequently queried
- **Finance**: Financial transactions — requires strict integrity

### Why Repository + Service Pattern?
1. **Testability**: Interfaces allow mocking in tests
2. **Flexibility**: Repository implementations can be swapped (e.g., Eloquent → Redis cache layer)
3. **Separation of concerns**: Controllers handle HTTP, Services handle business logic, Repositories handle data access

### Why JWT Over Sanctum for API Auth?
The application uses JWT (`php-open-source-saver/jwt-auth`) for stateless API authentication with refresh token rotation. Sanctum is installed but its middleware has been removed from the API group to avoid conflicts with cookie-based JWT handling.

### Why Redis for Rate Limiting?
Redis Lua scripts provide atomic, single-round-trip rate limiting with sliding window semantics. This is more accurate and performant than Laravel's built-in rate limiter for the dual-window (IP + IP+Email) approach required by the login flow.