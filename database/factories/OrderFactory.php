<?php

namespace Database\Factories;

use App\Modules\Core\Models\Tenant;
use App\Modules\Core\Models\User;
use App\Modules\Financial\Models\Order;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    protected $model = Order::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'user_id' => User::factory(),
            'order_number' => fake()->unique()->numerify('ORD-########'),
            'status' => 'pending',
            'origin_type' => 'enrollment',
            'subtotal_cents' => fake()->numberBetween(0, 50000),
            'tax_cents' => fake()->numberBetween(0, 5000),
            'total_cents' => fake()->numberBetween(0, 55000),
            'source_key' => fake()->optional()->uuid(),
            'metadata' => ['channel' => 'internal'],
        ];
    }
}
