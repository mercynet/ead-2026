<?php

namespace Database\Factories;

use App\Models\Course;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Enrollment>
 */
class EnrollmentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'user_id' => User::factory(),
            'course_id' => Course::factory(),
            'status' => fake()->randomElement(['active', 'expired', 'pending']),
            'expires_at' => fake()->optional()->dateTimeBetween('+1 day', '+1 year'),
            'progress_percentage' => fake()->numberBetween(0, 100),
        ];
    }
}
