<?php

namespace Database\Factories;

use App\Modules\Core\Models\Tenant;
use App\Modules\Learning\Models\Lesson;
use App\Modules\Learning\Models\LessonMedia;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<LessonMedia>
 */
class LessonMediaFactory extends Factory
{
    protected $model = LessonMedia::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'lesson_id' => Lesson::factory(),
            'media_type' => 'video',
            'provider' => 'embed',
            'provider_ref' => fake()->uuid(),
            'url' => fake()->url(),
            'content' => null,
            'duration_seconds' => fake()->numberBetween(60, 3600),
            'sort_order' => fake()->numberBetween(1, 10),
            'is_active' => true,
            'metadata' => [],
        ];
    }
}
