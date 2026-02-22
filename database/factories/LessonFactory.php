<?php

namespace Database\Factories;

use App\Models\CourseModule;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Lesson>
 */
class LessonFactory extends Factory
{
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
