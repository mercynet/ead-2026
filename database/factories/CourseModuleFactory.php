<?php

namespace Database\Factories;

use App\Modules\Core\Models\Tenant;
use App\Modules\Learning\Models\Course;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Modules\Learning\Models\CourseModule>
 */
class CourseModuleFactory extends Factory
{
    protected $model = \App\Modules\Learning\Models\CourseModule::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'course_id' => Course::factory(),
            'title' => fake()->sentence(3),
            'sort_order' => fake()->numberBetween(1, 20),
        ];
    }
}
