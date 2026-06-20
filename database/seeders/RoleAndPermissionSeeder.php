<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RoleAndPermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */

    public function run(): void
    {
        // dung: Xóa bộ nhớ đệm quyền hạn để đảm bảo rằng các thay đổi được áp dụng ngay lập tức
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Tạo quyền (nếu chưa có thì tạo, có rồi thì bỏ qua)
        Permission::firstOrCreate(['name' => 'manage finances', 'guard_name' => 'api']);
        Permission::firstOrCreate(['name' => 'manage library', 'guard_name' => 'api']);
        Permission::firstOrCreate(['name' => 'record discipline', 'guard_name' => 'api']);
        Permission::firstOrCreate(['name' => 'manage events', 'guard_name' => 'api']);
        Permission::firstOrCreate(['name' => 'manage school structure', 'guard_name' => 'api']);
        Permission::firstOrCreate(['name' => 'manage users', 'guard_name' => 'api']);
        Permission::firstOrCreate(['name' => 'view schedules', 'guard_name' => 'api']);
        Permission::firstOrCreate(['name' => 'manage schedules', 'guard_name' => 'api']);

        // Tạo role
        $studentRole   = Role::firstOrCreate(['name' => 'student', 'guard_name' => 'api']);
        $parentRole    = Role::firstOrCreate(['name' => 'parent', 'guard_name' => 'api']);
        $teacherRole   = Role::firstOrCreate(['name' => 'teacher', 'guard_name' => 'api']);
        $accountantRole = Role::firstOrCreate(['name' => 'accountant', 'guard_name' => 'api']);
        $librarianRole = Role::firstOrCreate(['name' => 'librarian', 'guard_name' => 'api']);
        $principalRole = Role::firstOrCreate(['name' => 'principal', 'guard_name' => 'api']);
        $redScarfRole  = Role::firstOrCreate(['name' => 'red_scarf', 'guard_name' => 'api']);
        $adminRole     = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'api']);

        // Gán quyền
        $teacherRole->givePermissionTo(['record discipline', 'manage events', 'view schedules', 'manage schedules']);
        $accountantRole->givePermissionTo('manage finances');
        $librarianRole->givePermissionTo('manage library');
        $redScarfRole->givePermissionTo('record discipline');
        $studentRole->givePermissionTo('view schedules');
        $parentRole->givePermissionTo('view schedules');

        $principalRole->givePermissionTo([
            'manage school structure',
            'manage events',
            'manage users',
            'view schedules',
            'manage schedules',
        ]);

        $adminRole->givePermissionTo(Permission::all());
    }
}
