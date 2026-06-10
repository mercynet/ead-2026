<?php

namespace Database\Factories;

use App\Modules\Core\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Modules\Learning\Models\Category>
 */
class CategoryFactory extends Factory
{
    protected $model = \App\Modules\Learning\Models\Category::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->words(2, true);

        return [
            'tenant_id' => Tenant::factory(),
            'parent_id' => null,
            'name' => Str::title($name),
            'slug' => Str::slug($name),
            'normalized_name' => (string) Str::of($name)->ascii()->lower()->squish(),
            'is_system' => false,
        ];
    }

    public function system(): static
    {
        return $this->state(fn (): array => [
            'tenant_id' => null,
            'is_system' => true,
        ]);
    }
}
