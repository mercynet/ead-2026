<?php

namespace Database\Factories;

use App\Modules\Core\Models\User;
use App\Modules\Financial\Models\Order;
use App\Modules\Financial\Models\OrderItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrderItem>
 */
class OrderItemFactory extends Factory
{
    protected $model = OrderItem::class;

    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'itemable_type' => User::class,
            'itemable_id' => User::factory(),
            'item_snapshot' => [
                'name' => fake()->words(3, true),
                'quantity' => 1,
            ],
            'price_cents' => fake()->numberBetween(100, 50000),
        ];
    }
}
