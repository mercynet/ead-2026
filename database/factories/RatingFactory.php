<?php

namespace Database\Factories;

use App\Modules\Core\Models\User;
use App\Modules\Learning\Models\Course;
use App\Modules\Learning\Models\Lesson;
use App\Modules\Learning\Models\Rating;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Rating>
 */
class RatingFactory extends Factory
{
    protected $model = Rating::class;

    public function definition(): array
    {
        return [
            'rateable_type' => Course::class,
            'rateable_id' => Course::factory(),
            'tenant_id' => fn (array $attributes): ?int => Course::query()->find($attributes['rateable_id'])?->tenant_id,
            'user_id' => User::factory(),
            'stars' => fake()->numberBetween(1, 5),
            'reaction' => fake()->optional()->randomElement(['like', 'dislike']),
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
