<?php

namespace Database\Factories;

use App\Models\CourseModule;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

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
        return [
            'tenant_id' => Tenant::factory(),
            'course_module_id' => CourseModule::factory(),
            'title' => fake()->sentence(4),
            'sort_order' => fake()->numberBetween(1, 30),
            'is_free' => fake()->boolean(),
        ];
    }
}
