<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Tenant>
 */
class TenantFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->company();

        return [
            'name' => $name,
            'domain' => str(fake()->unique()->slug(2))->lower()->append('.local')->toString(),
            'database' => null,
            'description' => fake()->sentence(),
            'is_active' => true,
        ];
    }
}
