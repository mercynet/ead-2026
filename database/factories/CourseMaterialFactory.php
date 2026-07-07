<?php

namespace Database\Factories;

use App\Modules\Learning\Models\Course;
use App\Modules\Learning\Models\CourseMaterial;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CourseMaterial>
 */
class CourseMaterialFactory extends Factory
{
    protected $model = CourseMaterial::class;

    public function definition(): array
    {
        return [
            'course_id' => Course::factory(),
            'tenant_id' => fn (array $attributes): ?int => Course::query()->find($attributes['course_id'])?->tenant_id,
            'instructor_id' => fn (array $attributes): ?int => Course::query()->find($attributes['course_id'])?->instructor_id,
            'file_path' => 'tenants/'.fake()->numberBetween(1, 999).'/materials/'.fake()->unique()->slug().'.pdf',
        ];
    }
}
