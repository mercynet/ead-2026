<?php

namespace Database\Factories;

use App\Models\QuizAttempt;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\QuizAttemptAnswer>
 */
class QuizAttemptAnswerFactory extends Factory
{
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'quiz_attempt_id' => QuizAttempt::factory(),
            'question_snapshot' => [
                'question' => fake()->sentence().'?',
                'type' => 'single_choice',
                'options' => [
                    ['text' => fake()->sentence()],
                    ['text' => fake()->sentence()],
                    ['text' => fake()->sentence()],
                    ['text' => fake()->sentence()],
                ],
                'correct_options' => [0],
            ],
            'selected_options' => [fake()->numberBetween(0, 3)],
            'is_correct' => fake()->boolean(),
            'points_earned' => fake()->randomElement([0, 1]),
            'answered_at' => now(),
        ];
    }

    public function correct(): static
    {
        return $this->state(fn (): array => [
            'is_correct' => true,
            'points_earned' => 1,
        ]);
    }

    public function incorrect(): static
    {
        return $this->state(fn (): array => [
            'is_correct' => false,
            'points_earned' => 0,
        ]);
    }
}
