<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Student;
use App\Models\Profile;
use App\Models\SchoolClass;
use App\Models\AcademicYear;
use App\Models\StudentGuardian;

class StructureSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('Setting up school structure (Classes, Students, Guardians)...');

        $activeYear = AcademicYear::where('is_active', true)->firstOrFail();
        $teachers = User::role('teacher')->get();
        $parents = User::role('parent')->get()->shuffle();
        $parentIndex = 0;

        // 1. Tạo Lớp học và gán GVCN
        $classes = SchoolClass::factory()->count(15)->create([
            'academic_year_id' => $activeYear->id,
            'homeroom_teacher_id' => fn() => $teachers->isEmpty() ? null : $teachers->random()->id,
        ]);

        // 2. Tạo Học sinh, gán vào lớp và liên kết với Phụ huynh
        $progressBar = $this->command->getOutput()->createProgressBar($classes->count());
        $progressBar->start();

        $classes->each(function ($class) use ($parents, &$parentIndex, $progressBar) {
            $studentCount = rand(30, 40);

            for ($i = 0; $i < $studentCount; $i++) {
                // Tạo User account và Profile cho học sinh
                $user = User::factory()->has(Profile::factory())->create()->assignRole('student');
                // Tạo record Student
                $student = Student::factory()->create(['user_id' => $user->id, 'class_id' => $class->id]);

                // Gán 1 hoặc 2 phụ huynh cho học sinh này
                for ($j = 0; $j < rand(1, 2); $j++) {
                    if ($parentIndex >= $parents->count()) $parentIndex = 0; // Quay vòng danh sách phụ huynh nếu hết

                    StudentGuardian::factory()->create([
                        'student_id' => $student->id,
                        'guardian_user_id' => $parents[$parentIndex]->id,
                    ]);
                    $parentIndex++;
                }
            }
            $progressBar->advance();
        });

        $progressBar->finish();
        $this->command->info("\nSchool structure created successfully.");
    }
}
