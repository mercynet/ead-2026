<?php

namespace App\Modules\Financial\Gateways\Adapters;

use App\Modules\Financial\Gateways\Contracts\PaymentGatewayInterface;
use App\Modules\Financial\Gateways\Data\ChargeIntent;
use App\Modules\Financial\Gateways\Data\ChargeResult;

class CashPaymentGateway implements PaymentGatewayInterface
{
    public function identifier(): string
    {
        return 'cash';
    }

    public function label(): string
    {
        return 'Dinheiro';
    }

    public function charge(array $credentials, ChargeIntent $intent): ChargeResult
    {
        return new ChargeResult(
            successful: true,
            status: 'pending',
            raw: [
                'payment_method' => 'cash',
                'payment_flow' => 'manual_confirmation_required',
            ],
        );
    }

    public function validateConfiguration(array $config): bool
    {
        return true;
    }
}
