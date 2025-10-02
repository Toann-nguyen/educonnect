<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\DisciplineType;
use App\Models\Student;
use App\Models\User;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Discipline>
 */
class DisciplineFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $locations = ['Lớp học', 'Sân trường', 'Hành lang', 'Cổng trường', 'Phòng thí nghiệm', 'Thư viện', 'Sân bóng'];

        return [
            'incident_date' => fake()->dateTimeBetween('-3 months', 'now')->format('Y-m-d'),
            'incident_location' => fake()->randomElement($locations),
            'description' => fake()->sentence(15),
            'status' => fake()->randomElement(['pending', 'confirmed', 'confirmed', 'confirmed']), // 75% confirmed
            'parent_notified' => fake()->boolean(60), // 60% đã thông báo
            'parent_notified_at' => function (array $attributes) {
                return $attributes['parent_notified'] ? now()->subDays(rand(1, 7)) : null;
            },
            'attachments' => null,
        ];
    }

    /** State: Vi phạm đã được duyệt */
    public function confirmed(): static
    {
        return $this->state(fn(array $attributes) => [
            'status' => 'confirmed',
            'reviewed_at' => now()->subDays(rand(1, 14)),
            'review_note' => fake()->sentence(),
            'parent_notified' => true,
            'parent_notified_at' => now()->subDays(rand(1, 10)),
        ]);
    }

    /** State: Vi phạm đang chờ duyệt */
    public function pending(): static
    {
        return $this->state(fn(array $attributes) => [
            'status' => 'pending',
            'reviewed_by_user_id' => null,
            'reviewed_at' => null,
            'review_note' => null,
            'parent_notified' => false,
            'parent_notified_at' => null,
        ]);
    }

    /** State: Vi phạm bị từ chối */
    public function rejected(): static
    {
        return $this->state(fn(array $attributes) => [
            'status' => 'rejected',
            'reviewed_at' => now()->subDays(rand(1, 7)),
            'review_note' => 'Không đủ bằng chứng / Sự việc không rõ ràng',
            'parent_notified' => false,
        ]);
    }

    /** State: Vi phạm có khiếu nại */
    public function appealed(): static
    {
        return $this->state(fn(array $attributes) => [
            'status' => 'appealed',
            'reviewed_at' => now()->subDays(rand(7, 21)),
            'parent_notified' => true,
        ]);
    }
}
