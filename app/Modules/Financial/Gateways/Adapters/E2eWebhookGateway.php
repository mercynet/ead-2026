<?php

namespace App\Modules\Financial\Gateways\Adapters;

use App\Modules\Financial\Contracts\GatewayConfigurationDefinition;
use App\Modules\Financial\Enums\PaymentChargeStatus;
use App\Modules\Financial\Enums\PaymentConfirmationMode;
use App\Modules\Financial\Gateways\Contracts\PaymentGatewayInterface;
use App\Modules\Financial\Gateways\Data\ChargeIntent;
use App\Modules\Financial\Gateways\Data\ChargeResult;

/**
 * Gateway determinístico usado somente pelo harness HTTP E2E.
 *
 * O provider só o registra em `testing`/`e2e`; não é uma opção de pagamento de
 * produção nem representa um PSP first-party.
 */
class E2eWebhookGateway implements PaymentGatewayInterface
{
    public function identifier(): string
    {
        return 'e2e-webhook';
    }

    public function label(): string
    {
        return 'E2E webhook';
    }

    public function confirmationMode(): PaymentConfirmationMode
    {
        return PaymentConfirmationMode::Automatic;
    }

    public function configurationSchema(): GatewayConfigurationDefinition
    {
        return new GatewayConfigurationDefinition(
            identifier: $this->identifier(),
            label: $this->label(),
            fields: [
                'webhook_secret' => [
                    'label' => 'Segredo do webhook E2E',
                    'input' => 'password',
                    'required' => true,
                    'secret' => true,
                    'rules' => ['string', 'min:8'],
                ],
            ],
        );
    }

    public function charge(array $credentials, ChargeIntent $intent): ChargeResult
    {
        return new ChargeResult(
            status: PaymentChargeStatus::Pending,
            externalId: 'e2e-'.$intent->reference,
            raw: ['source' => 'e2e'],
        );
    }

    public function validateConfiguration(array $config): bool
    {
        return isset($config['webhook_secret']) && is_string($config['webhook_secret']);
    }
}
