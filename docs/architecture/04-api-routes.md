# API Routes

## Route Files

All routes are loaded by `RouteServiceProvider`. The application uses 4 route files:

| File | Path | Middleware Group | Purpose |
|------|------|-----------------|---------|
| web | `routes/web.php` | web | Root health endpoint |
| api | `routes/api.php` | api | Health check only |
| identity | `routes/identity.php` | api (JWT + role/permission) | Auth, roles, permissions, user management |
| school | `routes/school.php` | api (JWT + role/permission) | School CRUD, schedules, grades, disciplines, etc. |
| channels | `routes/channels.php` | — | Broadcasting channels |

## Route Registration Order

Routes are registered in `RouteServiceProvider` in this order:
1. `routes/web.php` — Web middleware group
2. `routes/api.php` — API middleware group (prefix `/api`)
3. `routes/identity.php` — Identity domain routes
4. `routes/school.php` — School domain routes
5. `routes/channels.php` — Broadcasting channels
6. `routes/console.php` — Console commands

## Identity Routes (`routes/identity.php`)

### Public Auth Endpoints
```
POST   /api/auth/register          → AuthController@register
POST   /api/auth/login             → AuthController@login
POST   /api/auth/forgot-password   → AuthController@forgotPassword
POST   /api/auth/reset-password    → AuthController@resetPassword
POST   /api/auth/email/verify      → AuthController@verifyEmail
POST   /api/auth/refresh           → AuthController@refresh
```

### Authenticated Endpoints (JWT required)
```
GET    /api/auth/me                → AuthController@user
POST   /api/auth/logout            → AuthController@logout
POST   /api/auth/logout/all        → AuthController@logoutAll
POST   /api/auth/email/verify/send → EmailController@send
PUT    /api/auth/password/change   → PasswordController@change

GET    /api/auth/sessions          → SessionController@index
DELETE /api/auth/sessions/{id}     → SessionController@destroy

POST   /api/auth/2fa/totp/setup             → TwoFactorController@setup
POST   /api/auth/2fa/totp/enable            → TwoFactorController@enable
POST   /api/auth/2fa/totp/disable           → TwoFactorController@disable
GET    /api/auth/2fa/backup-codes           → TwoFactorController@backupCodes
POST   /api/auth/2fa/backup-codes/regenerate → TwoFactorController@regenerateBackupCodes
```

### Admin-Only Routes (JWT + role:admin)
```
GET    /api/admin/roles                           → RoleController@index
POST   /api/admin/roles                           → RoleController@store
GET    /api/admin/roles/{id}                      → RoleController@show
PUT    /api/admin/roles/{id}                      → RoleController@update
DELETE /api/admin/roles/{id}                      → RoleController@destroy
GET    /api/permissions                            → PermissionController@index
GET    /api/permissions/{id}                       → PermissionController@show
POST   /api/admin/permissions                     → PermissionController@store
PUT    /api/admin/permissions/{id}                → PermissionController@update
DELETE /api/admin/permissions/{id}                → PermissionController@destroy
GET    /api/admin/roles/{id}/permissions          → RoleController@getRolePermissions
POST   /api/admin/roles/{id}/permissions         → RoleController@assignPermissions
DELETE /api/admin/roles/{role}/permissions/{permission} → RoleController@removePermission
GET    /api/admin/users/{userId}/roles            → UserRoleController@getUserRoles
GET    /api/admin/users/{userId}/permissions      → UserRoleController@getUserPermissions
POST   /api/admin/users/{userId}/roles            → UserRoleController@assign
DELETE /api/admin/users/{userId}/roles/{role}     → UserRoleController@revoke
```

## School Routes (`routes/school.php`)

All school routes require JWT authentication (`auth.jwt` middleware).

### Dashboard
```
GET /api/dashboard  → DashBoardController@index
```

### Admin-Only Resources (role:admin)
```
GET/POST   /api/admin/academic-years  → AcademicYearController
GET/PUT/DELETE /api/admin/academic-years/{id}
GET/POST   /api/admin/classes         → SchoolClassController
GET/PUT/DELETE /api/admin/classes/{id}
GET/POST   /api/admin/subjects        → SubjectController
GET/PUT/DELETE /api/admin/subjects/{id}
```

### Schedules
```
GET  /api/schedules/class/{class}              → ScheduleController@getByClass
GET  /api/schedules/class/{class}/week         → ScheduleController@getWeeklySchedule
GET  /api/schedules/my                         → ScheduleController@mySchedule (teacher|student)
GET  /api/schedules/my-classes                 → ScheduleController@getTeacherClasses (teacher)
GET/POST/PUT/DELETE /api/schedules/            → ScheduleController (admin|principal|teacher)
POST /api/schedules/{id}/restore               → ScheduleController@restore (admin|principal|teacher)
```

### Grades
```
GET  /api/my-grades                            → GradeController@myGrades (student|parent)
GET/POST/PUT/DELETE /api/grades/               → GradeController (teacher|admin)
```

### Disciplines
```
GET    /api/disciplines                        → DisciplineController@index
GET    /api/disciplines/my                     → DisciplineController@my (student|parent)
GET    /api/disciplines/class/{classId}        → DisciplineController@byClass (admin|principal|teacher)
GET    /api/disciplines/statistics             → DisciplineController@statistics (admin|principal)
GET    /api/disciplines/student/{studentId}    → DisciplineController@byStudent (admin|principal|teacher)
GET    /api/disciplines/export                 → DisciplineController@export (admin|principal)
GET/PUT/DELETE /api/disciplines/{discipline}   → DisciplineController
POST   /api/disciplines                        → DisciplineController@store (permission:record discipline)
POST   /api/disciplines/{discipline}/approve   → DisciplineController@approve (admin|principal)
POST   /api/disciplines/{discipline}/reject    → DisciplineController@reject (admin|principal)
POST   /api/disciplines/{discipline}/appeal    → DisciplineController@appeal (student|parent)
```

### Discipline Types (admin|principal)
```
GET/POST/PUT/DELETE /api/discipline-types/     → DisciplineTypeController
```

### Conduct Scores
```
GET  /api/conduct-scores/my                    → ConductScoreController@my (student|parent)
GET  /api/conduct-scores/class/{classId}       → ConductScoreController@byClass (admin|principal|teacher)
GET  /api/conduct-scores/student/{studentId}   → ConductScoreController@byStudent (admin|principal|teacher)
POST /api/conduct-scores/                      → ConductScoreController@store (admin|principal|teacher)
PUT  /api/conduct-scores/{conductScore}        → ConductScoreController@update (teacher|admin|principal)
POST /api/conduct-scores/{conductScore}/approve → ConductScoreController@approve (admin|principal)
POST /api/conduct-scores/recalculate           → ConductScoreController@recalculate (admin|principal)
```

### Student (parent only)
```
GET /api/my-children  → StudentController@myChildren (role:parent)
```

## API Health Check

```
GET /api/health  → returns { status: "healthy", timestamp: "..." }
```

## Middleware Stack for API Routes

Applied in order:
1. `auth.jwt` — JWT token validation (custom middleware)
2. `role` — Role check (JsonRoleMiddleware using Spatie)
3. `permission` — Permission check (Spatie PermissionMiddleware)
4. `role_or_permission` — Either role or permission check
5. `rate.limit` / `rate.register` — Rate limiting
6. `idempotence` — Idempotency key checking
7. `validate.api` — API access validation
8. `secure-headers` — Security headers

## Route Parameter Conventions

| Parameter | Type | Description |
|-----------|------|-------------|
| `{id}` | int | Route model binding by numeric ID |
| `{class}` | int | Class ID (school) |
| `{studentId}` | int | Student ID |
| `{classId}` | int | Class ID |
| `{discipline}` | int | Discipline record ID |
| `{disciplineType}` | int | Discipline type ID |
| `{conductScore}` | int | Conduct score ID |
| `{schedule}` | int | Schedule ID |
| `{grade}` | int | Grade ID |
| `{userId}` | int | User ID |
| `{invoiceId}` | int | Invoice ID |
| `{paymentId}` | int | Payment ID |