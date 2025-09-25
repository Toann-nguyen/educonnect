<?php

namespace Database\Seeders;

use App\Models\Profile;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    private string $defaultPassword;

    public function __construct()
    {
        // Use consistent password for all seeded users
        $this->defaultPassword = Hash::make('password123');
    }

    private function createCoreUser(string $email, string $fullName, string|array $roles): User
    {
        $user = User::firstOrCreate(
            ['email' => $email],
            array_merge(
                User::factory()->make()->toArray(),
                ['password' => $this->defaultPassword]
            )
        );

        if (!$user->profile) {
            $user->profile()->save(Profile::factory()->make(['full_name' => $fullName]));
        }

        $user->syncRoles($roles);

        return $user;
    }

    public function run(): void
    {
        $this->command->info('Creating core users...');

        // Create core system users
        $this->createCoreUser('admin@educonnect.com', 'Admin User', 'admin');
        $this->createCoreUser('principal@educonnect.com', 'Principal User', 'principal');
        $this->createCoreUser('teacher@educonnect.com', 'Teacher User', 'teacher');
        $this->createCoreUser('parent@educonnect.com', 'Parent User', 'parent');
        $this->createCoreUser('redscarf@educonnect.com', 'Red Scarf User', ['student', 'red_scarf']);
        $this->createCoreUser('student@educonnect.com', 'Student User', 'student');
        $this->createCoreUser('accountant@educonnect.com', 'Accountant User', 'accountant');
        $this->createCoreUser('librarian@educonnect.com', 'Librarian User', 'librarian');

        $this->command->info('Creating bulk random users...');

        // Create random users with same password for testing
        User::factory()
            ->count(20)
            ->has(Profile::factory())
            ->state(['password' => $this->defaultPassword])
            ->create()
            ->each(fn($user) => $user->assignRole('teacher'));

        User::factory()
            ->count(100)
            ->has(Profile::factory())
            ->state(['password' => $this->defaultPassword])
            ->create()
            ->each(fn($user) => $user->assignRole('parent'));
    }
}
