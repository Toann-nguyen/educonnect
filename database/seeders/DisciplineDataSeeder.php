<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\AcademicYear;
use App\Models\Discipline;
use App\Models\DisciplineAction;
use App\Models\DisciplineAppeal;
use App\Models\DisciplineType;
use App\Models\Student;
use App\Models\StudentConductScore;
use App\Models\User;

class DisciplineDataSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('Seeding discipline data...');

        $students = Student::with('schoolClass')->get();
        $teachers = User::role('teacher')->get();
        $principals = User::role(['admin', 'principal'])->get();
        $disciplineTypes = DisciplineType::active()->get();
        $activeYear = AcademicYear::where('is_active', true)->first();

        if ($students->isEmpty() || $teachers->isEmpty() || $disciplineTypes->isEmpty()) {
            $this->command->warn('Missing required data. Skipping discipline seeding.');
            return;
        }

        // 1. Tạo các bản ghi kỷ luật
        $this->command->info('Creating discipline records...');
        $progressBar = $this->command->getOutput()->createProgressBar(100);
        $progressBar->start();

        $disciplines = collect();

        // 30% học sinh có ít nhất 1 vi phạm
        foreach ($students->random(floor($students->count() * 0.3)) as $student) {
            $violationCount = rand(1, 4); // Mỗi học sinh có 1-4 vi phạm

            for ($i = 0; $i < $violationCount; $i++) {
                $disciplineType = $disciplineTypes->random();
                $reporter = $teachers->random();
                $reviewer = $principals->isNotEmpty() ? $principals->random() : null;

                // 80% đã được duyệt, 15% đang chờ, 5% bị từ chối
                $statusChance = rand(1, 100);
                if ($statusChance <= 80) {
                    $discipline = Discipline::factory()
                        ->confirmed()
                        ->create([
                            'student_id' => $student->id,
                            'discipline_type_id' => $disciplineType->id,
                            'reporter_user_id' => $reporter->id,
                            'reviewed_by_user_id' => $reviewer ? $reviewer->id : null,
                            'penalty_points' => $disciplineType->default_penalty_points,
                        ]);

                    // 70% có hành động xử lý
                    if (rand(1, 10) <= 7) {
                        // Quyết định loại hành động dựa trên mức độ nghiêm trọng
                        $actionType = match ($disciplineType->severity_level) {
                            'light' => 'warning',
                            'medium' => fake()->randomElement(['warning', 'parent_meeting']),
                            'serious' => fake()->randomElement(['parent_meeting', 'detention']),
                            'very_serious' => fake()->randomElement(['suspension', 'detention']),
                            default => 'warning',
                        };

                        DisciplineAction::factory()->completed()->create([
                            'discipline_id' => $discipline->id,
                            'action_type' => $actionType,
                            'executed_by_user_id' => $reviewer ? $reviewer->id : $reporter->id,
                        ]);
                    }

                    // 10% có khiếu nại
                    if (rand(1, 10) === 1) {
                        $appellant = rand(1, 2) === 1 ? $student->user : ($student->guardians->isNotEmpty() ? $student->guardians->random()->guardian : null);

                        if ($appellant) {
                            DisciplineAppeal::factory()->create([
                                'discipline_id' => $discipline->id,
                                'appellant_user_id' => $appellant->id,
                                'appellant_type' => $appellant->hasRole('student') ? 'student' : 'parent',
                                'reviewed_by_user_id' => $reviewer ? $reviewer->id : null,
                            ]);

                            // Nếu có khiếu nại, update status
                            if (rand(1, 2) === 1) {
                                $discipline->update(['status' => 'appealed']);
                            }
                        }
                    }
                } elseif ($statusChance <= 95) {
                    $discipline = Discipline::factory()
                        ->pending()
                        ->create([
                            'student_id' => $student->id,
                            'discipline_type_id' => $disciplineType->id,
                            'reporter_user_id' => $reporter->id,
                            'penalty_points' => $disciplineType->default_penalty_points,
                        ]);
                } else {
                    $discipline = Discipline::factory()
                        ->rejected()
                        ->create([
                            'student_id' => $student->id,
                            'discipline_type_id' => $disciplineType->id,
                            'reporter_user_id' => $reporter->id,
                            'reviewed_by_user_id' => $reviewer ? $reviewer->id : null,
                            'penalty_points' => $disciplineType->default_penalty_points,
                        ]);
                }

                $disciplines->push($discipline);
            }

            if ($progressBar->getProgress() < 100) {
                $progressBar->advance();
            }
        }

        $progressBar->finish();
        $this->command->newLine();

        // 2. Tạo điểm hạnh kiểm cho học sinh
        $this->command->info('Creating conduct scores for students...');

        if (!$activeYear) {
            $this->command->warn('No active academic year found. Skipping conduct scores.');
            return;
        }

        foreach ($students as $student) {
            foreach ([1, 2] as $semester) {
                // Tính tổng điểm trừ từ các vi phạm đã confirmed trong học kỳ
                $semesterStart = $semester === 1
                    ? $activeYear->start_date
                    : $activeYear->start_date->copy()->addMonths(5);

                $semesterEnd = $semester === 1
                    ? $activeYear->start_date->copy()->addMonths(5)
                    : $activeYear->end_date;

                $totalPenalty = Discipline::where('student_id', $student->id)
                    ->where('status', 'confirmed')
                    ->whereBetween('incident_date', [$semesterStart, $semesterEnd])
                    ->sum('penalty_points');

                // Lấy GVCN để comment
                $homeroomTeacher = $student->schoolClass
                    ? $student->schoolClass->homeroomTeacher
                    : null;

                StudentConductScore::factory()->create([
                    'student_id' => $student->id,
                    'semester' => $semester,
                    'academic_year_id' => $activeYear->id,
                    'total_penalty_points' => $totalPenalty,
                    'approved_by_user_id' => $principals->isNotEmpty() ? $principals->random()->id : null,
                ]);
            }
        }

        $this->command->info('Discipline data seeded successfully.');
        $this->command->info('Total disciplines created: ' . $disciplines->count());
    }
}
