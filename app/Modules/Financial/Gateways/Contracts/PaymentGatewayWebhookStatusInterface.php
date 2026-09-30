<?php

namespace App\Modules\Financial\Gateways\Contracts;

use App\Modules\Financial\Enums\PaymentChargeStatus;

interface PaymentGatewayWebhookStatusInterface
{
    /**
     * Consulta o estado autoritativo de uma cobrança referenciada por webhook.
     *
     * @param  array<string, mixed>  $credentials
     */
    public function resolveWebhookStatus(array $credentials, string $externalId): PaymentChargeStatus;
}
