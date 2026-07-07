<?php

namespace Database\Factories;

use App\Modules\Core\Models\User;
use App\Modules\Learning\Models\CourseMaterial;
use App\Modules\Learning\Models\MaterialDownload;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MaterialDownload>
 */
class MaterialDownloadFactory extends Factory
{
    protected $model = MaterialDownload::class;

    public function definition(): array
    {
        return [
            'course_material_id' => CourseMaterial::factory(),
            'tenant_id' => fn (array $attributes): ?int => CourseMaterial::query()->find($attributes['course_material_id'])?->tenant_id,
            'user_id' => User::factory(),
            'ip_address' => fake()->ipv4(),
            'user_agent' => fake()->userAgent(),
            'downloaded_at' => now(),
        ];
    }
}
