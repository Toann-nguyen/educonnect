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
        Permission::firstOrCreate(['name' => 'manage finances']);     // Quản lý tài chính
        Permission::firstOrCreate(['name' => 'manage library']);      // Quản lý thư viện
        Permission::firstOrCreate(['name' => 'record discipline']);   // Ghi nhận kỷ luật
        Permission::firstOrCreate(['name' => 'manage events']);       // Quản lý sự kiện
        Permission::firstOrCreate(['name' => 'manage school structure']);
        Permission::firstOrCreate(['name' => 'manage users']);
        Permission::firstOrCreate(['name' => 'view schedules']);      // Xem thời khóa biểu
        Permission::firstOrCreate(['name' => 'manage schedules']);    // Quản lý thời khóa biểu

        // Tạo role
        $studentRole   = Role::firstOrCreate(['name' => 'student']);
        $parentRole    = Role::firstOrCreate(['name' => 'parent']);
        $teacherRole   = Role::firstOrCreate(['name' => 'teacher']);
        $accountantRole = Role::firstOrCreate(['name' => 'accountant']);
        $librarianRole = Role::firstOrCreate(['name' => 'librarian']);
        $principalRole = Role::firstOrCreate(['name' => 'principal']);
        $redScarfRole  = Role::firstOrCreate(['name' => 'red_scarf']);
        $adminRole     = Role::firstOrCreate(['name' => 'admin']);

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
