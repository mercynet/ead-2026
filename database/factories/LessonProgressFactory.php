<?php

namespace Database\Factories;

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\LessonProgress;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class LessonProgressFactory extends Factory
{
    protected $model = LessonProgress::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'user_id' => User::factory(),
            'course_id' => Course::factory(),
            'enrollment_id' => Enrollment::factory(),
            'lesson_id' => Lesson::factory(),
            'started_at' => now(),
            'completed_at' => null,
            'time_spent_seconds' => fake()->numberBetween(0, 3600),
            'progress_percentage' => fake()->numberBetween(0, 100),
            'is_completed' => false,
            'current_time_seconds' => fake()->numberBetween(0, 1800),
            'total_time_seconds' => fake()->numberBetween(1800, 3600),
            'last_watched_at' => now(),
        ];
    }

    public function completed(): static
    {
        return $this->state(fn (): array => [
            'is_completed' => true,
            'completed_at' => now(),
            'progress_percentage' => 100,
        ]);
    }
}
