<?php

namespace Database\Factories;

use App\Modules\Learning\Models\Course;
use App\Modules\Learning\Models\Lesson;
use App\Modules\Learning\Models\RatingStats;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RatingStats>
 */
class RatingStatsFactory extends Factory
{
    protected $model = RatingStats::class;

    public function definition(): array
    {
        return [
            'rateable_type' => Course::query()->getModel()->getMorphClass(),
            'rateable_id' => Course::factory(),
            'tenant_id' => fn (array $attributes): ?int => Course::query()->find($attributes['rateable_id'])?->tenant_id,
            'average_stars' => fake()->randomFloat(2, 0, 5),
            'total_ratings' => fake()->numberBetween(0, 50),
            'five_stars' => fake()->numberBetween(0, 20),
            'four_stars' => fake()->numberBetween(0, 20),
            'three_stars' => fake()->numberBetween(0, 20),
            'two_stars' => fake()->numberBetween(0, 20),
            'one_star' => fake()->numberBetween(0, 20),
            'likes_count' => fake()->numberBetween(0, 30),
            'dislikes_count' => fake()->numberBetween(0, 30),
            'last_rated_at' => now(),
        ];
    }

    public function forCourse(Course $course): static
    {
        return $this->state(fn (): array => [
            'rateable_type' => $course->getMorphClass(),
            'rateable_id' => $course->id,
            'tenant_id' => $course->tenant_id,
        ]);
    }

    public function forLesson(Lesson $lesson): static
    {
        return $this->state(fn (): array => [
            'rateable_type' => $lesson->getMorphClass(),
            'rateable_id' => $lesson->id,
            'tenant_id' => $lesson->tenant_id,
        ]);
    }
}
