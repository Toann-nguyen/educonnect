<?php

namespace Database\Seeders;

use App\Enums\PermissionEnum;
use App\Enums\RoleEnum;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RoleAndPermissionSeeder extends Seeder
{
    /**
     * T1.4 RBAC — spatie/laravel-permission v6.25
     * Seed 4 core roles: Principal / Homeroom / Teacher / Student
     * + extended roles for backward compat (admin, parent, accountant, librarian, red_scarf)
     * Permissions theo §10.2 + PermissionEnum (29 perms) + legacy spaced perms (8).
     */
    public function run(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // ── 1. Permissions ──────────────────────────────────────────────
        // Enum canonical (underscore) — guard api
        foreach (PermissionEnum::values() as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'api']);
        }

        // Legacy spaced names for backward compat (routes checking 'manage finances' etc.)
        $legacy = [
            'manage finances',
            'manage library',
            'record discipline',
            'manage events',
            'manage school structure',
            'manage users',
            'view schedules',
            'manage schedules',
        ];
        foreach ($legacy as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'api']);
        }

        // ── 2. Roles ───────────────────────────────────────────────────
        foreach (RoleEnum::values() as $roleName) {
            Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'api']);
        }

        // Backward compat: ensure 'homeroom' alias also covers 'homeroom_teacher' if ever used
        // (no duplicate — homeroom is canonical per T1.4)

        // ── 3. §10.2 Permission matrix ─────────────────────────────────
        // Principal: full school authority — all enum perms + legacy spaced
        $principalPerms = PermissionEnum::values();

        // Homeroom (GVCN): class management, grades, attendance, discipline, schedules
        $homeroomPerms = [
            PermissionEnum::VIEW_USERS->value,
            PermissionEnum::MANAGE_CLASSES->value,
            PermissionEnum::MANAGE_SCHOOL_STRUCTURE->value,
            PermissionEnum::VIEW_SCHEDULES->value,
            PermissionEnum::MANAGE_SCHEDULES->value,
            PermissionEnum::VIEW_GRADES->value,
            PermissionEnum::MANAGE_GRADES->value,
            PermissionEnum::VIEW_ATTENDANCE->value,
            PermissionEnum::MANAGE_ATTENDANCE->value,
            PermissionEnum::VIEW_LIBRARY->value,
            PermissionEnum::BORROW_BOOKS->value,
            PermissionEnum::VIEW_DISCIPLINE->value,
            PermissionEnum::RECORD_DISCIPLINE->value,
            PermissionEnum::MANAGE_DISCIPLINE->value,
            PermissionEnum::VIEW_EVENTS->value,
            PermissionEnum::MANAGE_EVENTS->value,
            PermissionEnum::REGISTER_EVENTS->value,
            // legacy spaced equivalents (so hasPermission('record discipline') also passes)
            'record discipline',
            'view schedules',
            'manage schedules',
            'manage events',
        ];

        // Teacher: teaching scoped
        $teacherPerms = [
            PermissionEnum::VIEW_SCHEDULES->value,
            PermissionEnum::MANAGE_SCHEDULES->value,
            PermissionEnum::VIEW_GRADES->value,
            PermissionEnum::MANAGE_GRADES->value,
            PermissionEnum::VIEW_ATTENDANCE->value,
            PermissionEnum::MANAGE_ATTENDANCE->value,
            PermissionEnum::VIEW_LIBRARY->value,
            PermissionEnum::VIEW_DISCIPLINE->value,
            PermissionEnum::RECORD_DISCIPLINE->value,
            PermissionEnum::VIEW_EVENTS->value,
            PermissionEnum::REGISTER_EVENTS->value,
            'view schedules',
            'manage schedules',
            'record discipline',
        ];

        // Student: read-only + borrow/register
        $studentPerms = [
            PermissionEnum::VIEW_SCHEDULES->value,
            PermissionEnum::VIEW_GRADES->value,
            PermissionEnum::VIEW_ATTENDANCE->value,
            PermissionEnum::VIEW_LIBRARY->value,
            PermissionEnum::BORROW_BOOKS->value,
            PermissionEnum::VIEW_DISCIPLINE->value,
            PermissionEnum::VIEW_EVENTS->value,
            PermissionEnum::REGISTER_EVENTS->value,
            'view schedules',
        ];

        // ── 4. Assign (sync) ───────────────────────────────────────────
        $this->syncRole('principal', $principalPerms);
        $this->syncRole('homeroom', $homeroomPerms);
        $this->syncRole('teacher', $teacherPerms);
        $this->syncRole('student', $studentPerms);

        // Extended roles (keep existing behavior)
        $this->syncRole('parent', [PermissionEnum::VIEW_SCHEDULES->value, PermissionEnum::VIEW_GRADES->value, PermissionEnum::VIEW_ATTENDANCE->value, PermissionEnum::VIEW_EVENTS->value, 'view schedules']);
        $this->giveIfNotHas('accountant', ['manage finances', PermissionEnum::MANAGE_FINANCES->value, PermissionEnum::VIEW_INVOICES->value, PermissionEnum::MANAGE_INVOICES->value, PermissionEnum::MANAGE_PAYMENTS->value]);
        $this->giveIfNotHas('librarian', ['manage library', PermissionEnum::MANAGE_LIBRARY->value, PermissionEnum::VIEW_LIBRARY->value, PermissionEnum::BORROW_BOOKS->value]);
        $this->giveIfNotHas('red_scarf', ['record discipline', PermissionEnum::RECORD_DISCIPLINE->value, PermissionEnum::VIEW_DISCIPLINE->value]);
        $this->syncRole('admin', Permission::where('guard_name', 'api')->pluck('name')->toArray());

        // Legacy teacher extra (ensure idempotent)
        $this->giveIfNotHas('teacher', ['manage events', PermissionEnum::MANAGE_EVENTS->value]);

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
        $this->command?->info('✓ T1.4 RBAC seeded: roles=' . Role::where('guard_name', 'api')->count() . ' perms=' . Permission::where('guard_name', 'api')->count());
    }

    private function syncRole(string $roleName, array $permissions): void
    {
        $role = Role::where('name', $roleName)->where('guard_name', 'api')->first();
        if (!$role) return;
        // filter to existing permissions only
        $valid = Permission::whereIn('name', $permissions)->where('guard_name', 'api')->pluck('name')->toArray();
        $role->syncPermissions($valid);
    }

    private function giveIfNotHas(string $roleName, array $permissions): void
    {
        $role = Role::where('name', $roleName)->where('guard_name', 'api')->first();
        if (!$role) return;
        $valid = Permission::whereIn('name', $permissions)->where('guard_name', 'api')->pluck('name')->toArray();
        foreach ($valid as $perm) {
            if (!$role->hasPermissionTo($perm)) {
                $role->givePermissionTo($perm);
            }
        }
    }
}
