<?php

namespace Database\Factories;

use App\Modules\Core\Models\Tenant;
use App\Modules\Core\Models\User;
use App\Modules\Learning\Models\LessonMedia;
use App\Modules\Learning\Models\LessonMediaProgress;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LessonMediaProgress>
 */
class LessonMediaProgressFactory extends Factory
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
            'lesson_media_id' => LessonMedia::factory(),
            'watched_seconds' => fake()->numberBetween(0, 3600),
            'completion_percentage' => fake()->randomFloat(2, 0, 100),
            'watch_sessions' => [],
            'is_completed' => false,
            'completed_at' => null,
            'last_watched_at' => now(),
        ];
    }
}
