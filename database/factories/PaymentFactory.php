<?php

namespace Database\Factories;

use App\Modules\Financial\Models\Order;
use App\Modules\Financial\Models\Payment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
{
    protected $model = Payment::class;

    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'status' => 'pending',
            'external_id' => fake()->optional()->uuid(),
            'gateway_response' => ['status' => 'pending'],
            'metadata' => ['provider' => 'internal'],
        ];
    }
}
