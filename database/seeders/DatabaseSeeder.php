<?php

namespace Database\Seeders;

use App\Domains\School\Models\AcademicYear;
use Illuminate\Database\Seeder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        Model::unguard(); // Tạm thời tắt bảo vệ mass assignment để tránh lỗi

        // \App\Domains\Identity\Models\User::factory(10)->create();
        // \App\Domains\Identity\Models\User::factory()->create(['name' => 'Test User', 'email' => 'test@example.com']);

        $this->command->warn('🚀 Starting database seeding... This may take a moment.');

        try {
            // BƯỚC 1: TẠO CÁC ĐỊNH NGHĨA CƠ BẢN
            $this->command->info('📋 Step 1: Seeding roles, permissions, and subjects...');
            $this->call([
                RoleAndPermissionSeeder::class,
                SubjectSeeder::class,
                FeeTypeSeeder::class,
                DisciplineTypeSeeder::class,
            ]);

            // BƯỚC 2: TẠO NĂM HỌC
            $this->command->info('📅 Step 2: Creating academic years...');
            AcademicYear::firstOrCreate(
                ['name' => '2023-2024'],
                ['start_date' => '2023-09-05', 'end_date' => '2024-05-25', 'is_active' => false]
            );
            AcademicYear::firstOrCreate(
                ['name' => '2024-2025'],
                ['start_date' => '2024-09-05', 'end_date' => '2025-05-25', 'is_active' => true]
            );

            // BƯỚC 3: TẠO CÁC USER VỚI VAI TRÒ
            $this->command->info('👥 Step 3: Creating users with roles...');
            $this->call(UserSeeder::class);

            // BƯỚC 4: TẠO CẤU TRÚC TRƯỜNG HỌC (Lớp, Học sinh, Phụ huynh)
            $this->command->info('🏫 Step 4: Setting up school structure...');
            $this->call(StructureSeeder::class);

            // BƯỚC 5: TẠO DỮ LIỆU HÓA ĐƠN VÀ THANH TOÁN
            $this->command->info('💰 Step 5: Creating invoices and payments...');
            $this->call(InvoiceAndPaymentSeeder::class);

            // BƯỚC 5: TẠO CÁC DỮ LIỆU GIAO DỊCH (Điểm, Hóa đơn, Thư viện, Sự kiện...)
            $this->command->info('📊 Step 5: Creating transactional data...');
            $this->call(TransactionDataSeeder::class);

            // BƯỚC 6: TẠO DỮ LIỆU KỶ LUẬT VÀ ĐIỂM HẠNH KIỂM
            $this->command->info('⚖️ Step 6: Creating discipline records and conduct scores...');
            $this->call(DisciplineDataSeeder::class);
            // HIỂN THỊ THỐNG KÊ CUỐI CÙNG
            $this->displayFinalStats();
            $this->command->info('✅ Database seeding completed successfully!');
        } catch (\Exception $e) {
            $this->command->error('❌ Seeding failed: ' . $e->getMessage());
            Log::error('Seeding error: ' . $e->getMessage());
            throw $e; // Ném lỗi để dừng quá trình nếu có vấn đề
        } finally {
            Model::reguard(); // Bật lại bảo vệ mass assignment
        }
    }

    /**
     * Hiển thị thống kê cuối cùng sau khi seed
     */
    private function displayFinalStats()
    {
        $this->command->line('');
        $this->command->line('📈 <fg=yellow>DATABASE SEEDING STATISTICS:</fg=yellow>');
        $this->command->line('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');

        // Import models cần thiết
        $stats = [
            'Users' => \App\Domains\Identity\Models\User::count(),
            'Students' => \App\Domains\School\Models\Student::count(),
            'Teachers' => \App\Domains\Identity\Models\User::role('teacher')->count(),
            'Parents' => \App\Domains\Identity\Models\User::role('parent')->count(),
            'Classes' => \App\Domains\School\Models\SchoolClass::count(),
            'Academic Years' => AcademicYear::count(),
            'Subjects' => \App\Domains\School\Models\Subject::count(),
            'Schedules' => \App\Domains\School\Models\Schedule::count(),
            'Grades' => \App\Domains\School\Models\Grade::count(),
            'Attendances' => \App\Domains\School\Models\Attendance::count(),
            'Invoices' => \App\Domains\Finance\Models\Invoice::count(),
            'Payments' => App\Domains\Finance\Models\Payment::count(),
            'Library Books' => \App\Domains\School\Models\LibraryBook::count(),
            'Library Transactions' => \App\Domains\School\Models\LibraryTransaction::count(),
            'Events' => \App\Domains\School\Models\Event::count(),
            'Event Registrations' => \App\Domains\School\Models\EventRegistration::count(),
            'Disciplines' => \App\Domains\School\Models\Discipline::count(),
        ];
        foreach ($stats as $label => $count) {
            $this->command->line(sprintf(
                '<fg=cyan>%-20s</fg=cyan>: <fg=green>%s</fg=green>',
                $label,
                number_format($count)
            ));
        }
        $this->command->line('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');

        // Hiển thị tài khoản mặc định
        $this->command->line('');
        $this->command->line('🔑 <fg=yellow>DEFAULT LOGIN ACCOUNTS:</fg=yellow>');
        $this->command->line('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');

        $accounts = [
            ['Role' => 'Admin', 'Email' => 'admin@educonnect.com', 'Password' => 'admin123'],
            ['Role' => 'Principal', 'Email' => 'principal@educonnect.com', 'Password' => 'password'],
            ['Role' => 'Teacher', 'Email' => 'teacher@educonnect.com', 'Password' => 'teacher123'],
            ['Role' => 'Student', 'Email' => 'student@educonnect.com', 'Password' => 'student123'],
            ['Role' => 'Parent', 'Email' => 'parent@educonnect.com', 'Password' => 'password'],
            ['Role' => 'Accountant', 'Email' => 'accountant@educonnect.com', 'Password' => 'password'],
            ['Role' => 'Librarian', 'Email' => 'librarian@educonnect.com', 'Password' => 'password'],
        ];
        foreach ($accounts as $account) {
            $this->command->line(sprintf(
                '<fg=yellow>%-12s</fg=yellow>: <fg=cyan>%-30s</fg=cyan> | <fg=green>%s</fg=green>',
                $account['Role'],
                $account['Email'],
                $account['Password']
            ));
        }
        $this->command->line('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');

        // Thông tin bổ sung
        $this->command->line('');
        $this->command->line('💡 <fg=yellow>ADDITIONAL INFO:</fg=yellow>');
        $this->command->line('• Active Academic Year: <fg=green>' . AcademicYear::where('is_active', true)->first()->name . '</fg=green>');
        $this->command->line('• All students have been assigned to classes');
        $this->command->line('• Sample attendance records created for recent dates');
        $this->command->line('• Library books available for borrowing');
        $this->command->line('• Upcoming events ready for registration');
        $this->command->line('');
    }
}
