<?php

namespace Database\Factories;

use App\Modules\Core\Models\Tenant;
use App\Modules\Ecosystem\Models\Plugin;
use App\Modules\Ecosystem\Models\PluginActivation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PluginActivation>
 */
class PluginActivationFactory extends Factory
{
    protected $model = PluginActivation::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'plugin_id' => Plugin::factory(),
            'status' => 'active',
            'activated_at' => now(),
            'deactivated_at' => null,
            'activated_by' => null,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => [
            'status' => 'inactive',
            'deactivated_at' => now(),
        ]);
    }
}
