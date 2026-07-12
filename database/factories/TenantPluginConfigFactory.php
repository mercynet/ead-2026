<?php

namespace Database\Factories;

use App\Modules\Core\Models\Tenant;
use App\Modules\Ecosystem\Models\Plugin;
use App\Modules\Ecosystem\Models\TenantPluginConfig;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TenantPluginConfig>
 */
class TenantPluginConfigFactory extends Factory
{
    protected $model = TenantPluginConfig::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'plugin_id' => Plugin::factory(),
            'config' => ['configured' => true],
            'enabled' => true,
        ];
    }

    public function disabled(): static
    {
        return $this->state(fn (): array => ['enabled' => false]);
    }
}
