<?php

namespace Database\Factories;

use App\Modules\Learning\Models\CourseMaterial;
use App\Modules\Learning\Models\MaterialStats;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MaterialStats>
 */
class MaterialStatsFactory extends Factory
{
    protected $model = MaterialStats::class;

    public function definition(): array
    {
        return [
            'course_material_id' => CourseMaterial::factory(),
            'tenant_id' => fn (array $attributes): ?int => CourseMaterial::query()->find($attributes['course_material_id'])?->tenant_id,
            'total_downloads' => fake()->numberBetween(0, 500),
            'downloads_today' => fake()->numberBetween(0, 20),
            'downloads_week' => fake()->numberBetween(0, 80),
            'downloads_month' => fake()->numberBetween(0, 200),
            'last_downloaded_at' => now(),
        ];
    }
}
