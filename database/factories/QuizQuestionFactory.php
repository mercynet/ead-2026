<?php

namespace Database\Factories;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\QuizQuestion>
 */
class QuizQuestionFactory extends Factory
{
    public function definition(): array
    {
        $options = [
            ['text' => fake()->sentence(), 'correct' => false],
            ['text' => fake()->sentence(), 'correct' => false],
            ['text' => fake()->sentence(), 'correct' => false],
            ['text' => fake()->sentence(), 'correct' => true],
        ];

        shuffle($options);

        return [
            'tenant_id' => Tenant::factory(),
            'instructor_id' => User::factory(),
            'question' => fake()->sentence().'?',
            'type' => 'single_choice',
            'options' => $options,
            'correct_options' => [0],
            'explanation' => fake()->paragraph(),
            'points' => 1,
            'is_active' => true,
        ];
    }

    public function multipleChoice(): static
    {
        return $this->state(fn (): array => [
            'type' => 'multiple_choice',
            'correct_options' => [0, 2],
        ]);
    }

    public function trueFalse(): static
    {
        return $this->state(fn (): array => [
            'type' => 'true_false',
            'options' => [
                ['text' => 'Verdadeiro', 'correct' => true],
                ['text' => 'Falso', 'correct' => false],
            ],
            'correct_options' => [0],
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => ['is_active' => false]);
    }
}
