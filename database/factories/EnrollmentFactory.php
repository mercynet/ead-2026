<?php

namespace Database\Factories;

use App\Modules\Core\Models\Tenant;
use App\Modules\Core\Models\User;
use App\Modules\Learning\Models\Course;
use Illuminate\Database\Eloquent\Factories\Factory;

class EnrollmentFactory extends Factory
{
    protected $model = \App\Modules\Learning\Models\Enrollment::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'user_id' => User::factory(),
            'course_id' => Course::factory(),
            'billing_type' => null,
            'status' => fake()->randomElement(['active', 'expired', 'pending', 'cancelled']),
            'enrolled_at' => now(),
            'completed_at' => null,
            'access_expires_at' => fake()->optional()->dateTimeBetween('+1 day', '+1 year'),
            'progress_percentage' => fake()->numberBetween(0, 100),
        ];
    }

    public function active(): static
    {
        return $this->state(fn (): array => [
            'status' => 'active',
            'access_expires_at' => now()->addDays(30),
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn (): array => [
            'status' => 'expired',
            'access_expires_at' => now()->subDay(),
        ]);
    }

    public function pending(): static
    {
        return $this->state(fn (): array => [
            'status' => 'pending',
            'access_expires_at' => null,
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn (): array => [
            'status' => 'cancelled',
            'access_expires_at' => null,
        ]);
    }
}
