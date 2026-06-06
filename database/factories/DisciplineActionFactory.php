<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\DisciplineAction>
 */
class DisciplineActionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $actionType = fake()->randomElement(['warning', 'parent_meeting', 'detention', 'suspension']);

        $descriptions = [
            'warning' => 'Nhắc nhở học sinh về hành vi vi phạm',
            'parent_meeting' => 'Gặp phụ huynh để trao đổi về tình hình học sinh',
            'detention' => 'Học sinh phải ở lại sau giờ học để làm bài tập phạt',
            'suspension' => 'Đình chỉ học ' . rand(1, 5) . ' ngày',
            'expulsion' => 'Đề xuất đuổi học vĩnh viễn'
        ];

        return [
            'action_type' => $actionType,
            'action_description' => $descriptions[$actionType],
            'completion_status' => fake()->randomElement(['completed', 'completed', 'in_progress', 'scheduled']),
            'executed_at' => function (array $attributes) {
                return $attributes['completion_status'] === 'completed'
                    ? now()->subDays(rand(1, 14))
                    : null;
            },
        ];
    }

    /** State: Hành động đã hoàn thành */
    public function completed(): static
    {
        return $this->state(fn(array $attributes) => [
            'completion_status' => 'completed',
            'executed_at' => now()->subDays(rand(1, 14)),
        ]);
    }

    /** State: Hành động đang chờ */
    public function scheduled(): static
    {
        return $this->state(fn(array $attributes) => [
            'completion_status' => 'scheduled',
            'executed_at' => null,
        ]);
    }
}
