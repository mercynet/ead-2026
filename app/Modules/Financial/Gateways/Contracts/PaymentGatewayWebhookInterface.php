<?php

namespace App\Modules\Financial\Gateways\Contracts;

interface PaymentGatewayWebhookInterface
{
    /**
     * Valida a assinatura do payload bruto recebido do provedor.
     *
     * @param  array<string, mixed>  $credentials
     * @param  array<string, mixed>  $context  provider request context, such as request_id or data_id
     */
    public function verifyWebhookSignature(array $credentials, string $payload, string $signature, array $context = []): bool;
}
