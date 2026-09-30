<?php

namespace App\Modules\Financial\Gateways\Contracts;

interface PaymentGatewayWebhookInterface
{
    /**
     * Valida a assinatura do payload bruto recebido do provedor.
     *
     * @param  array<string, mixed>  $credentials
     */
    public function verifyWebhookSignature(array $credentials, string $payload, string $signature): bool;
}
