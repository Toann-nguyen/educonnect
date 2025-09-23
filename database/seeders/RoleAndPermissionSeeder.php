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

        // Tạo các quyền hạn
        Permission::create(['name' => 'manage finances']);
        Permission::create(['name' => 'manage library']);
        Permission::create(['name' => 'record discipline']);
        Permission::create(['name' => 'manage events']);

        // Tạo các vai trò và gán quyền
        Role::create(['name' => 'student']);
        Role::create(['name' => 'parent']);
        $teacherRole = Role::create(['name' => 'teacher']);
        $teacherRole->givePermissionTo(['record discipline', 'manage events']);

        Role::create(['name' => 'accountant'])->givePermissionTo('manage finances');
        Role::create(['name' => 'librarian'])->givePermissionTo('manage library');

        $adminRole = Role::create(['name' => 'admin']);
        $adminRole->givePermissionTo(Permission::all()); // Admin có tất cả các quyền
    }
}
