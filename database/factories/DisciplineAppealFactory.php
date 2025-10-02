<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\DisciplineAppeal>
 */
class DisciplineAppealFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $appellantType = fake()->randomElement(['student', 'parent']);

        $reasons = [
            'Con em không có hành vi như mô tả trong bản ghi',
            'Sự việc xảy ra không đúng như báo cáo',
            'Con em bị oan, không phải là người vi phạm',
            'Mức phạt quá nặng so với hành vi vi phạm',
            'Có những tình tiết giảm nhẹ chưa được xem xét',
        ];

        return [
            'appellant_type' => $appellantType,
            'appeal_reason' => fake()->randomElement($reasons),
            'evidence' => null,
            'status' => fake()->randomElement(['pending', 'approved', 'rejected']),
            'reviewed_at' => function (array $attributes) {
                return $attributes['status'] !== 'pending' ? now()->subDays(rand(1, 7)) : null;
            },
            'review_response' => function (array $attributes) {
                if ($attributes['status'] === 'approved') {
                    return 'Sau khi xem xét, Ban Giám hiệu chấp nhận khiếu nại và điều chỉnh mức xử lý';
                } elseif ($attributes['status'] === 'rejected') {
                    return 'Sau khi xem xét, Ban Giám hiệu giữ nguyên quyết định xử lý';
                }
                return null;
            },
        ];
    }

    /** State: Khiếu nại đang chờ xử lý */
    public function pending(): static
    {
        return $this->state(fn(array $attributes) => [
            'status' => 'pending',
            'reviewed_by_user_id' => null,
            'reviewed_at' => null,
            'review_response' => null,
        ]);
    }

    /** State: Khiếu nại được chấp nhận */
    public function approved(): static
    {
        return $this->state(fn(array $attributes) => [
            'status' => 'approved',
            'reviewed_at' => now()->subDays(rand(1, 7)),
        ]);
    }
}
