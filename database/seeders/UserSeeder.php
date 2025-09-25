<?php

namespace Database\Seeders;

use App\Models\Profile;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;


class UserSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('Creating core users...');

        // 1. Tạo Admin
        User::firstOrCreate(
            ['email' => 'admin@educonnect.com'],
            [
                'password' => Hash::make('admin123'),
                'email_verified_at' => now(),
                'remember_token' => \Str::random(10),
                'status' => 1,
            ]
        )->has(Profile::factory()->state(['full_name' => 'Admin User']))->assignRole('admin');

        // 2. Tạo Hiệu trưởng
        User::firstOrCreate(
            ['email' => 'principal@educonnect.com'],
            [
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
                'remember_token' => \Str::random(10),
                'status' => 1,
            ]
        )->has(Profile::factory()->state(['full_name' => 'Principal User']))->assignRole('principal');

        // 3. Tạo Giáo viên
        User::firstOrCreate(
            ['email' => 'teacher@educonnect.com'],
            [
                'password' => Hash::make('teacher123'),
                'email_verified_at' => now(),
                'remember_token' => \Str::random(10),
                'status' => 1,
            ]
        )->has(Profile::factory()->state(['full_name' => 'Teacher User']))->assignRole('teacher');

        // 4. Tạo Học sinh
        User::firstOrCreate(
            ['email' => 'student@educonnect.com'],
            [
                'password' => Hash::make('student123'),
                'email_verified_at' => now(),
                'remember_token' => \Str::random(10),
                'status' => 1,
            ]
        )->has(Profile::factory()->state(['full_name' => 'Student User']))->assignRole('student');

        // 5. Tạo Phụ huynh
        User::firstOrCreate(
            ['email' => 'parent@educonnect.com'],
            [
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
                'remember_token' => \Str::random(10),
                'status' => 1,
            ]
        )->has(Profile::factory()->state(['full_name' => 'Parent User']))->assignRole('parent');

        // Tạo tài khoản học sinh với vai trò Cờ đỏ
        $redScarfUser = User::firstOrCreate(
            ['email' => 'redscarf@educonnect.com'],
            [
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
                'remember_token' => \Str::random(10),
                'status' => 1,
            ]
        )->has(Profile::factory()->state(['full_name' => 'Red Scarf User']));
        $redScarfUser->assignRole(['student', 'red_scarf']);

        // Tài khoản học sinh bình thường
        User::firstOrCreate(
            ['email' => 'student2@educonnect.com'],
            [
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
                'remember_token' => \Str::random(10),
                'status' => 1,
            ]
        )->has(Profile::factory()->state(['full_name' => 'Student User 2']))->assignRole('student');

        // Các vai trò nhân viên khác
        User::firstOrCreate(
            ['email' => 'accountant@educonnect.com'],
            [
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
                'remember_token' => \Str::random(10),
                'status' => 1,
            ]
        )->has(Profile::factory())->assignRole('accountant');

        User::firstOrCreate(
            ['email' => 'librarian@educonnect.com'],
            [
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
                'remember_token' => \Str::random(10),
                'status' => 1,
            ]
        )->has(Profile::factory())->assignRole('librarian');

        // Tạo nhiều giáo viên và phụ huynh ngẫu nhiên
        $this->command->info('Creating teachers and parents...');
        User::factory()->count(20)->has(Profile::factory())->create()->each(fn($user) => $user->assignRole('teacher'));
        User::factory()->count(100)->has(Profile::factory())->create()->each(fn($user) => $user->assignRole('parent'));
    }
}
