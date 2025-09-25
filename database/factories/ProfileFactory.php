<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Profile>
 */
class ProfileFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'full_name' => fake()->name(),
            'phone_number' => fake()->unique()->phoneNumber(),
            'birthday' => fake()->dateTimeBetween('-40 years', '-10 years')->format('Y-m-d'),
            'gender' => fake()->numberBetween(0, 1),
            'address' => fake()->address(),
            'avatar' => 'avatars/default.png',
        ];
    }
}
