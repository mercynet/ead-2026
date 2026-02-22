<?php

namespace Database\Factories;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Questionnaire>
 */
class QuestionnaireFactory extends Factory
{
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'instructor_id' => User::factory(),
            'title' => fake()->sentence(3),
            'description' => fake()->paragraph(),
            'type' => 'standalone',
            'quizable_id' => null,
            'quizable_type' => null,
            'passing_score' => 70,
            'time_limit_minutes' => null,
            'is_active' => true,
            'show_results' => true,
        ];
    }

    public function lesson(): static
    {
        return $this->state(fn (): array => ['type' => 'lesson']);
    }

    public function course(): static
    {
        return $this->state(fn (): array => ['type' => 'course']);
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => ['is_active' => false]);
    }
}
