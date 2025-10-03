<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Profile;
use App\Models\Student;
use App\Models\StudentGuardian;
use App\Models\SchoolClass;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class SingleParentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('Creating a single parent account linked to one student...');

        $password = Hash::make('password123');

        // Create parent user (will not be linked to other students)
        $parent = User::firstOrCreate(
            ['email' => 'parent_single@educonnect.com'],
            [
                'password' => $password,
                'email_verified_at' => now(),
                'status' => 1,
                'remember_token' => Str::random(10),
            ]
        );

        if (!$parent->profile) {
            $parent->profile()->save(Profile::factory()->make(['full_name' => 'Parent Single']));
        }

        $parent->syncRoles('parent');

        // Create a single student user and student record dedicated for this parent
        $studentUser = User::firstOrCreate(
            ['email' => 'student_single@educonnect.com'],
            [
                'password' => $password,
                'email_verified_at' => now(),
                'status' => 1,
                'remember_token' => Str::random(10),
            ]
        );

        if (!$studentUser->profile) {
            $studentUser->profile()->save(Profile::factory()->make(['full_name' => 'Student Single']));
        }

        $studentUser->assignRole('student');

        // Ensure there's at least one class to assign the student to
        $class = SchoolClass::first();
        if (!$class) {
            $class = SchoolClass::factory()->create();
        }

        $student = Student::firstOrCreate(
            ['user_id' => $studentUser->id],
            [
                'class_id' => $class->id,
                'student_code' => 'HS_SINGLE_' . rand(1000, 9999),
                'status' => 1,
            ]
        );

        // Link parent -> this student (if not already linked). We do not link this parent to any other student.
        StudentGuardian::firstOrCreate([
            'student_id' => $student->id,
            'guardian_user_id' => $parent->id,
        ], [
            'relationship' => 'parent',
        ]);

        $this->command->info("Created parent {$parent->email} linked to student {$studentUser->email}");
    }
}
