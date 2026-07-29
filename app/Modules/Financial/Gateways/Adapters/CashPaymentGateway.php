<?php

namespace App\Modules\Financial\Gateways\Adapters;

use App\Modules\Financial\Contracts\GatewayConfigurationDefinition;
use App\Modules\Financial\Enums\PaymentChargeStatus;
use App\Modules\Financial\Enums\PaymentConfirmationMode;
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

    public function confirmationMode(): PaymentConfirmationMode
    {
        return PaymentConfirmationMode::Manual;
    }

    public function configurationSchema(): GatewayConfigurationDefinition
    {
        return new GatewayConfigurationDefinition(
            identifier: $this->identifier(),
            label: $this->label(),
            fields: [],
        );
    }

    public function charge(array $credentials, ChargeIntent $intent): ChargeResult
    {
        return new ChargeResult(
            status: PaymentChargeStatus::Pending,
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
