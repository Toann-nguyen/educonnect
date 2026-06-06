<?php

namespace Database\Seeders;

use App\Models\Attendance;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use App\Models\User;
use App\Models\Event;
use App\Models\FeeType;
use App\Models\Grade;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Schedule;
use App\Models\Discipline;
use App\Models\LibraryBook;
use App\Models\SchoolClass;
use App\Models\EventRegistration;
use App\Models\LibraryTransaction;
use Illuminate\Database\Seeder;


class TransactionDataSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('Seeding transactional data (Schedules, Grades, Invoices, Payments etc.)...');

        // Lấy dữ liệu nền tảng đã có
        $students = Student::with('guardians')->get();
        $teachers = User::role('teacher')->get();
        $accountants = User::role('accountant')->get();
        $subjects = Subject::all();
        $classes = SchoolClass::all();
        $feeTypes = FeeType::all(); // Lấy tất cả các loại phí đã được seed

        // 1. Tạo Thời khóa biểu
        $schedules = collect();
        foreach ($classes as $class) {
            for ($day = 2; $day <= 6; $day++) { // T2 -> T6
                for ($period = 1; $period <= 4; $period++) { // 4 tiết buổi sáng
                    Schedule::factory()->create([
                        'class_id' => $class->id,
                        'subject_id' => $subjects->random()->id,
                        'teacher_id' => $teachers->random()->id,
                        'day_of_week' => $day,
                        'period' => $period,
                    ]);
                }
            }
        }
        // 2. Tạo Điểm danh cho các buổi học trong thời khóa biểu
        $this->command->info('Creating attendance records...');
        foreach ($schedules->take(100) as $schedule) { // Lấy 100 schedule đầu tiên
            $classStudents = Student::where('class_id', $schedule->class_id)->get();

            // Tạo điểm danh cho 10 ngày gần đây
            for ($i = 0; $i < 10; $i++) {
                $date = now()->subDays($i);

                // Chỉ tạo điểm danh cho thứ trong tuần của schedule
                if ($date->dayOfWeek + 1 == $schedule->day_of_week) {
                    foreach ($classStudents as $student) {
                        // 85% có mặt, 10% vắng, 5% trễ
                        $statusChance = rand(1, 100);
                        if ($statusChance <= 85) {
                            Attendance::factory()->create([
                                'student_id' => $student->id,
                                'schedule_id' => $schedule->id,
                                'date' => $date->format('Y-m-d'),
                                'status' => 'present'
                            ]);
                        } elseif ($statusChance <= 95) {
                            Attendance::factory()->absent()->create([
                                'student_id' => $student->id,
                                'schedule_id' => $schedule->id,
                                'date' => $date->format('Y-m-d')
                            ]);
                        } else {
                            Attendance::factory()->late()->create([
                                'student_id' => $student->id,
                                'schedule_id' => $schedule->id,
                                'date' => $date->format('Y-m-d')
                            ]);
                        }
                    }
                }
            }
        }

        // 3. Tạo Điểm số
        $this->command->info('Creating grades...');
        foreach ($students as $student) {
            foreach ($subjects->random(rand(3, 5)) as $subject) {
                Grade::factory()->count(rand(2, 4))->create([
                    'student_id' => $student->id,
                    'subject_id' => $subject->id,
                    'teacher_id' => $teachers->random()->id,
                ]);
            }
        }

        // 4. Tạo Hóa đơn và Thanh toán
        $this->command->info('Creating invoices and payments...');
        foreach ($students->random(floor($students->count() * 0.8)) as $student) {
            $invoice = Invoice::factory()->create(['student_id' => $student->id]);
            if (rand(1, 10) <= 9 && $student->guardians->isNotEmpty()) {
                $guardian = $student->guardians->random();
                if ($guardian) {
                    Payment::factory()->create([
                        'invoice_id' => $invoice->id,
                        'amount_paid' => $invoice->total_amount,
                        'payer_user_id' => $guardian->guardian_user_id,
                        'created_by_user_id' => $accountants->random()->id,
                    ]);
                    $invoice->update(['status' => 'paid',  'paid_amount' => $invoice->total_amount]);
                }
            }
        }

        // 5. Tạo Sách và Giao dịch thư viện
        $books = LibraryBook::factory()->count(100)->create();
        foreach ($students->random(50) as $student) { // 50 học sinh mượn sách
            // 70% đã trả sách, 30% vẫn đang mượn
            if (rand(1, 10) <= 7) {
                LibraryTransaction::factory()->returned()->create([
                    'book_id' => $books->random()->id,
                    'user_id' => $student->user_id,
                ]);
            } else {
                LibraryTransaction::factory()->create([
                    'book_id' => $books->random()->id,
                    'user_id' => $student->user_id,
                ]);
            }
        }

        // 6. Tạo Sự kiện và Đăng ký
        $events = Event::factory()->count(5)->create();
        foreach ($events as $event) {
            foreach ($students->random(rand(20, 50)) as $student) {
                EventRegistration::factory()->create([
                    'event_id' => $event->id,
                    'student_id' => $student->id,
                ]);
            }
        }

        // // 7. Tạo Vi phạm kỷ luật
        // foreach ($students->random(20) as $student) { // 20 học sinh có vi phạm
        //     Discipline::factory()->create([
        //         'student_id' => $student->id,
        //         'reporter_user_id' => $teachers->random()->id,
        //     ]);
        // }
        // 8. Tạo Hóa đơn với FeeTypes
        $this->command->info('Creating invoices with fee types...');
        $issuer = $accountants->first() ?? User::role('admin')->first();

        foreach ($students->random(floor($students->count() * 0.8)) as $student) {
            // Tạo invoice cho tháng hiện tại
            $invoice = Invoice::create([
                'invoice_number' => Invoice::generateInvoiceNumber(),
                'student_id' => $student->id,
                'notes' => 'Học phí tháng ' . now()->format('m/Y'),
                'total_amount' => 0, // Sẽ tính sau
                'paid_amount' => 0,
                'due_date' => now()->addDays(15),
                'status' => 'unpaid',
                'issued_by' => $issuer->id,
            ]);

            // Gắn 2-4 loại phí ngẫu nhiên
            $selectedFeeTypes = $feeTypes->random(rand(2, 4));
            $totalAmount = 0;
            $feeTypeData = [];

            foreach ($selectedFeeTypes as $feeType) {
                $amount = $feeType->default_amount * (rand(80, 120) / 100); // Biến động ±20%
                $totalAmount += $amount;
                $feeTypeData[$feeType->id] = [
                    'amount' => $amount,
                    'note' => null,
                ];
            }

            $invoice->feeTypes()->sync($feeTypeData);
            $invoice->update([
                'total_amount' => $totalAmount,
                // 'amount' => $totalAmount,
            ]);

            // 90% hóa đơn được thanh toán
            if (rand(1, 10) <= 9 && $student->guardians->isNotEmpty()) {
                $guardian = $student->guardians->random();
                if ($guardian && $guardian->guardian) {
                    // Thanh toán toàn bộ hoặc một phần
                    $paymentAmount = rand(1, 10) <= 8
                        ? $totalAmount  // 80% thanh toán đủ
                        : $totalAmount * (rand(50, 90) / 100); // 20% thanh toán một phần

                    Payment::factory()->create([
                        'invoice_id' => $invoice->id,
                        'amount_paid' => $paymentAmount,
                        'payer_user_id' => $guardian->guardian_user_id,
                        'created_by_user_id' => $issuer->id,
                    ]);

                    $invoice->paid_amount = $paymentAmount;
                    $invoice->updatePaymentStatus();
                }
            }
        }

        $this->command->info('Transactional data seeded successfully.');
    }
}
