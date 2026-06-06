<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Attendance>
 */
class AttendanceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'date' => fake()->dateTimeThisYear()->format('Y-m-d'),
            'status' => fake()->randomElement(['present', 'absent', 'late']),
            'note' => fake()->optional(0.3)->sentence(), // 30% có ghi chú
            // student_id và schedule_id sẽ được set trong seeder

        ];
    }

    public function absent()
    {
        return $this->state(function (array $attributes) {
            return [
                'status' => 'absent',
                'note' => fake()->randomElement([
                    'Vắng không phép',
                    'Ốm',
                    'Có việc gia đình',
                    'Vắng có phép'
                ])
            ];
        });
    }
    public function late()
    {
        return $this->state(function (array $attributes) {
            return [
                'status' => 'late',
                'note' => fake()->randomElement([
                    'Đến trễ 10 phút',
                    'Đến trễ 15 phút',
                    'Kẹt xe',
                    'Có việc đột xuất'
                ])
            ];
        });
    }
}
