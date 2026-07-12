<?php

namespace Database\Factories;

use App\Modules\Financial\Models\PlatformPaymentGateway;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PlatformPaymentGateway>
 */
class PlatformPaymentGatewayFactory extends Factory
{
    protected $model = PlatformPaymentGateway::class;

    public function definition(): array
    {
        return [
            'gateway_slug' => 'stripe',
            'configuration' => [
                'mode' => 'sandbox',
                'secret_key' => 'sk_test_'.fake()->uuid(),
            ],
            'is_active' => true,
            'is_default' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => ['is_active' => false]);
    }

    public function gateway(string $slug): static
    {
        return $this->state(fn (): array => ['gateway_slug' => $slug]);
    }
}
