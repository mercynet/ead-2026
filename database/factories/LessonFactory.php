<?php

namespace Database\Factories;

use App\Modules\Core\Models\Tenant;
use App\Modules\Learning\Models\CourseModule;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Modules\Learning\Models\Lesson>
 */
class LessonFactory extends Factory
{
    protected $model = \App\Modules\Learning\Models\Lesson::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $title = fake()->sentence(4);

        return [
            'tenant_id' => Tenant::factory(),
            'course_module_id' => CourseModule::factory(),
            'title' => $title,
            'slug' => Str::slug($title),
            'status' => 'published',
            'sort_order' => fake()->numberBetween(1, 30),
            'is_free' => fake()->boolean(),
            'is_active' => true,
        ];
    }
}
