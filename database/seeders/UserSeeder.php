<?php

namespace Database\Seeders;

use App\Models\Profile;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('Creating core users...');

        // 1. Tạo Admin
        User::factory()->has(Profile::factory()->state(['full_name' => 'Admin User']))
            ->create(['email' => 'admin@educonnect.com', 'password' => Hash::make('admin123')])
            ->assignRole('admin');

        // 2. Tạo các vai trò khác với tài khoản cụ thể
        User::factory()->has(Profile::factory())->create(['email' => 'accountant@educonnect.com'])->assignRole('accountant');
        User::factory()->has(Profile::factory())->create(['email' => 'librarian@educonnect.com'])->assignRole('librarian');
        User::factory()->has(Profile::factory())->create(['email' => 'teacher@educonnect.com'])->assignRole('teacher');

        // 3. Tạo nhiều giáo viên và phụ huynh ngẫu nhiên
        $this->command->info('Creating teachers and parents...');
        User::factory()->count(20)->has(Profile::factory())->create()->each(fn($user) => $user->assignRole('teacher'));
        User::factory()->count(100)->has(Profile::factory())->create()->each(fn($user) => $user->assignRole('parent'));
    }
}
