<?php

namespace App\Modules\Financial\Services\Webhooks;

use App\Modules\Financial\Gateways\Contracts\PaymentGatewayInterface;
use App\Modules\Financial\Gateways\Contracts\PaymentGatewayWebhookInterface;

class WebhookSignatureVerifier
{
    /**
     * @param  array<string, mixed>  $credentials
     * @param  array<string, mixed>  $context
     */
    public function verify(
        PaymentGatewayInterface $gateway,
        array $credentials,
        string $payload,
        ?string $signature,
        array $context = [],
    ): bool {
        if ($signature === null || $signature === '') {
            return false;
        }

        if ($gateway instanceof PaymentGatewayWebhookInterface) {
            return $gateway->verifyWebhookSignature($credentials, $payload, $signature, $context);
        }

        $secret = $credentials['webhook_secret'] ?? null;
        if (! is_string($secret) || $secret === '') {
            return false;
        }

        $provided = str_starts_with($signature, 'sha256=')
            ? substr($signature, strlen('sha256='))
            : $signature;

        if (preg_match('/\A[a-f0-9]{64}\z/i', $provided) !== 1) {
            return false;
        }

        return hash_equals(hash_hmac('sha256', $payload, $secret), strtolower($provided));
    }
}
