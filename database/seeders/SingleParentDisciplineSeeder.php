<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Student;
use App\Models\Discipline;
use App\Models\DisciplineType;

class SingleParentDisciplineSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('Seeding a discipline for student_single@educonnect.com...');

        $student = Student::whereHas('user', function ($q) {
            $q->where('email', 'student_single@educonnect.com');
        })->first();

        if (!$student) {
            $this->command->info('student_single not found, skipping');
            return;
        }

        $type = DisciplineType::first();
        if (!$type) {
            $type = DisciplineType::create([
                'code' => 'TEST',
                'name' => 'Test Violation',
                'severity_level' => 'light',
                'default_penalty_points' => 1,
            ]);
        }

        Discipline::create([
            'student_id' => $student->id,
            'discipline_type_id' => $type->id,
            'reporter_user_id' => User::where('email', 'teacher@educonnect.com')->first()->id ?? 1,
            'incident_date' => now()->toDateString(),
            'incident_location' => 'Test Location',
            'description' => 'Seeded test discipline for single parent',
            'penalty_points' => $type->default_penalty_points,
            'status' => 'confirmed',
            'parent_notified' => true,
            'parent_notified_at' => now(),
        ]);

        $this->command->info('Discipline seeded.');
    }
}
