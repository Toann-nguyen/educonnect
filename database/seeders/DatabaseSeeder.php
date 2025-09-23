<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;

use App\Models\AcademicYear;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // \App\Models\User::factory(10)->create();

        // \App\Models\User::factory()->create([
        //     'name' => 'Test User',
        //     'email' => 'test@example.com',
        // ]);
        $this->command->warn('Starting database seeding... This may take a moment.');

        // BƯỚC 1: TẠO CÁC ĐỊNH NGHĨA CƠ BẢN
        // Các seeder này không có sự phụ thuộc phức tạp, có thể chạy đầu tiên.
        $this->command->info('Step 1: Seeding roles, permissions, and subjects...');
        $this->call([
            RoleAndPermissionSeeder::class,
            SubjectSeeder::class,
        ]);

        // 2. Tạo năm học và kích hoạt 1 năm
        AcademicYear::factory()->create(['name' => '2023-2024', 'is_active' => false]);
        AcademicYear::factory()->create(['name' => '2024-2025', 'is_active' => true]);

        // 3. Tạo các User với vai trò
        $this->call(UserSeeder::class);

        // 4. Tạo cấu trúc trường học (Lớp, Học sinh, Phụ huynh)
        $this->call(StructureSeeder::class);

        // 5. Tạo các dữ liệu giao dịch (Điểm, Hóa đơn,...)
        // Phải chạy cuối cùng vì nó phụ thuộc vào tất cả các dữ liệu trên
        $this->call(TransactionDataSeeder::class);

        $this->command->info('Database seeding completed successfully.');
    }
}
