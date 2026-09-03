# Database Connections

## Overview

The application uses **3 separate database connections** to isolate data by domain:

| Connection | Default? | Purpose | Domain |
|-----------|----------|---------|--------|
| `mysql` | ✅ Yes | Default connection | Identity (primary) |
| `identity` | ❌ No | Identity domain data | Identity |
| `school` | ❌ No | School domain data | School |
| `finance` | ❌ No | Finance domain data | Finance |

## Connection Configuration

Defined in `config/database.php`. Each connection uses MySQL.

### `mysql` (default)
- Used by Identity domain models that don't explicitly set a connection
- Laravel's default auth tables, failed_jobs, personal_access_tokens

### `identity`
- Used by all Identity domain models: `User`, `Profile`, `Role`, `Permission`, `RefreshToken`, `UserSession`, `EmailVerification`, `PasswordResetToken`, `BackupCode`, `AuditLog`
- Configured via `protected $connection = 'identity'` on each model

### `school`
- Used by all School domain models: `Student`, `SchoolClass`, `Subject`, `Schedule`, `Grade`, `Attendance`, `Discipline`, etc.
- Configured via `protected $connection = 'school'` on each model

### `finance`
- Used by all Finance domain models: `Invoice`, `Payment`, `FeeType`, `InvoiceItem`
- Configured via `protected $connection = 'finance'` on each model
- ⚠️ **This connection is NOT defined in `config/database.php`** — it will cause `InvalidArgumentException` at runtime

## Migration Organization

Migrations are organized by domain:

```
database/migrations/
├── identity/                    # Identity domain migrations
│   ├── 2019_08_19_000000_create_failed_jobs_table.php
│   ├── 2019_12_14_000001_create_personal_access_tokens_table.php
│   ├── 2025_09_23_022523_create_permission_tables.php
│   ├── 2025_10_09_170802_add_soft_deletes_to_permissions_table.php
│   ├── 2025_10_09_171000_add_extra_fields_to_users_table.php
│   ├── 2026_05_18_072126_create_refresh_tokens_table.php
│   ├── 2026_05_18_072243_create_email_verifications_table.php
│   ├── 2026_05_19_053851_create_user_sessions_table.php
│   └── 2019_08_19_000000_create_failed_jobs_table.php
└── (default)                    # School domain migrations
    ├── 2025_09_23_031234_create_academic_years_table.php
    ├── 2025_09_23_031324_create_classes_table.php
    ├── 2025_09_23_031411_create_students_table.php
    ├── 2025_09_23_031519_create_student_guardians_table.php
    ├── 2025_09_23_031552_create_subjects_table.php
    ├── 2025_09_23_031635_create_schedules_table.php
    ├── 2025_09_23_032201_create_library_books_table.php
    ├── 2025_09_23_032251_create_library_transactions_table.php
    ├── 2025_09_23_032427_create_event_registrations_table.php
    ├── 2025_09_23_032501_create_notifications_table.php
    ├── 2025_09_30_022348_create_fee_types_table.php
    ├── 2025_09_30_022405_create_invoices_table.php
    ├── 2025_09_30_022506_create_payments_table.php
    ├── 2025_10_01_000407_add_soft_deletes_to_invoice_items_table.php
    ├── 2025_10_02_022437_create_discipline_types_table.php
    ├── 2025_10_02_043337_create_discipline_actions_table.php
    ├── 2025_10_02_043427_create_student_conduct_scores_table.php
    ├── 2025_10_02_043605_create_discipline_appeals_table.php
    └── 2025_09_26_070104_add_soft_deletes_to_schedules_table.php
```

## Model-to-Connection Mapping

### Identity Models (`$connection = 'identity'`)
- `App\Domains\Identity\Models\User`
- `App\Domains\Identity\Models\Profile`
- `App\Domains\Identity\Models\Role`
- `App\Domains\Identity\Models\Permission`
- `App\Domains\Identity\Models\RefreshToken`
- `App\Domains\Identity\Models\UserSession`
- `App\Domains\Identity\Models\EmailVerification`
- `App\Domains\Identity\Models\PasswordResetToken`
- `App\Domains\Identity\Models\BackupCode`
- `App\Domains\Identity\Models\AuditLog`

### School Models (`$connection = 'school'`)
- `App\Domains\School\Models\Student`
- `App\Domains\School\Models\SchoolClass`
- `App\Domains\School\Models\Subject`
- `App\Domains\School\Models\Schedule`
- `App\Domains\School\Models\Grade`
- `App\Domains\School\Models\Attendance`
- `App\Domains\School\Models\Discipline`
- `App\Domains\School\Models\DisciplineType`
- `App\Domains\School\Models\DisciplineAction`
- `App\Domains\School\Models\DisciplineAppeal`
- `App\Domains\School\Models\StudentConductScore`
- `App\Domains\School\Models\AcademicYear`
- `App\Domains\School\Models\Event`
- `App\Domains\School\Models\EventRegistration`
- `App\Domains\School\Models\LibraryBook`
- `App\Domains\School\Models\LibraryTransaction`
- `App\Domains\School\Models\StudentGuardian`

### Finance Models (`$connection = 'finance'`)
- `App\Domains\Finance\Models\Invoice`
- `App\Domains\Finance\Models\Payment`
- `App\Domains\Finance\Models\FeeType`
- `App\Domains\Finance\Models\InvoiceItem`

## Cross-Domain Queries

Models do NOT import models from other domains. Cross-domain relationships are resolved via `user_id`:

```php
// Identity User → School Student (via user_id, no model import)
public function getUserIdAttribute(): int
{
    return $this->id;
}

// School Student → Identity User (via user_id)
public function user()
{
    return $this->belongsTo(User::class); // Uses default connection
}
```

## Known Issues

| Issue | Severity | Detail |
|-------|----------|--------|
| Missing `finance` connection | 🔴 Critical | `config/database.php` has no `finance` connection definition. All Finance models will fail at runtime. |
| Mixed connection usage | 🟡 Medium | Some Identity models use `mysql` (default) while others explicitly use `identity`. Inconsistency may cause issues after migration. |