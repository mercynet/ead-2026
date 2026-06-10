<?php

namespace Database\Factories;

use App\Modules\Assessment\Models\Questionnaire;
use App\Modules\Core\Models\Tenant;
use App\Modules\Core\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Modules\Assessment\Models\QuizAttempt>
 */
class QuizAttemptFactory extends Factory
{
    protected $model = \App\Modules\Assessment\Models\QuizAttempt::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'user_id' => User::factory(),
            'questionnaire_id' => Questionnaire::factory(),
            'status' => 'in_progress',
            'questionnaire_snapshot' => [
                'title' => fake()->sentence(),
                'passing_score' => 70,
            ],
            'course_snapshot' => null,
            'module_snapshot' => null,
            'started_at' => now(),
            'finished_at' => null,
            'score' => null,
            'passed' => null,
            'time_spent_seconds' => 0,
        ];
    }

    public function completed(): static
    {
        return $this->state(fn (): array => [
            'status' => 'completed',
            'finished_at' => now(),
            'score' => fake()->numberBetween(0, 100),
            'passed' => true,
            'time_spent_seconds' => fake()->numberBetween(60, 3600),
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn (): array => [
            'status' => 'completed',
            'finished_at' => now(),
            'score' => fake()->numberBetween(0, 69),
            'passed' => false,
            'time_spent_seconds' => fake()->numberBetween(60, 3600),
        ]);
    }
}
