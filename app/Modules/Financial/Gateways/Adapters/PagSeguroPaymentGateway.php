<?php

namespace App\Modules\Financial\Gateways\Adapters;

use App\Modules\Financial\Contracts\GatewayConfigurationDefinition;
use App\Modules\Financial\Enums\PaymentChargeStatus;
use App\Modules\Financial\Enums\PaymentConfirmationMode;
use App\Modules\Financial\Gateways\Contracts\PaymentGatewayInterface;
use App\Modules\Financial\Gateways\Contracts\PaymentGatewayWebhookInterface;
use App\Modules\Financial\Gateways\Data\ChargeIntent;
use App\Modules\Financial\Gateways\Data\ChargeResult;
use Illuminate\Http\Client\Factory;
use InvalidArgumentException;
use RuntimeException;
use UnexpectedValueException;

class PagSeguroPaymentGateway implements PaymentGatewayInterface, PaymentGatewayWebhookInterface
{
    private readonly string $baseUrl;

    public function __construct(
        private readonly Factory $http,
        ?string $baseUrl = null,
    ) {
        $this->baseUrl = rtrim($baseUrl ?? (string) config('services.pagseguro.base_url', 'https://api.pagseguro.com'), '/');
    }

    public function identifier(): string
    {
        return 'pagseguro';
    }

    public function label(): string
    {
        return 'PagSeguro / PagBank';
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
                'access_token' => [
                    'label' => 'Token de acesso PagBank',
                    'input' => 'password',
                    'required' => true,
                    'secret' => true,
                    'rules' => ['string', 'min:8'],
                ],
                'webhook_public_key' => [
                    'label' => 'Chave pública de webhook PagBank',
                    'input' => 'password',
                    'required' => true,
                    'secret' => true,
                    'rules' => ['string', 'min:32'],
                ],
            ],
        );
    }

    public function charge(array $credentials, ChargeIntent $intent): ChargeResult
    {
        $accessToken = $credentials['access_token'] ?? null;

        if (! is_string($accessToken) || $accessToken === '') {
            throw new InvalidArgumentException('Configuração do PagBank inválida.');
        }

        $description = $intent->description ?? 'Compra de curso';
        $notificationUrl = rtrim((string) config('app.url'), '/').'/api/v1/webhooks/gateways/'.$this->identifier();
        $response = $this->http
            ->asJson()
            ->withToken($accessToken)
            ->withHeader('x-idempotency-key', $intent->idempotencyKey)
            ->post($this->baseUrl.'/checkouts', [
                'reference_id' => $intent->reference,
                'items' => [[
                    'reference_id' => $intent->reference,
                    'name' => $description,
                    'quantity' => 1,
                    'unit_amount' => $intent->amountCents,
                ]],
                'redirect_url' => config('app.url'),
                'return_url' => config('app.url'),
                'notification_urls' => [$notificationUrl],
                'payment_notification_urls' => [$notificationUrl],
            ]);

        if (! $response->successful()) {
            throw new RuntimeException('O PagBank não respondeu com um checkout válido.');
        }

        $data = $response->json();
        $externalId = is_array($data) ? ($data['id'] ?? null) : null;
        $status = is_array($data) ? ($data['status'] ?? 'ACTIVE') : null;
        $redirectUrl = $this->payLink($data);

        if (! is_string($externalId) || $externalId === '') {
            throw new UnexpectedValueException('O PagBank retornou um checkout inválido.');
        }

        if ($redirectUrl === null) {
            throw new UnexpectedValueException('O PagBank não retornou um link de checkout válido.');
        }

        return new ChargeResult(
            status: $this->mapStatus(is_string($status) ? $status : null),
            externalId: $externalId,
            redirectUrl: $redirectUrl,
            raw: [
                'id' => $externalId,
                'status' => $status,
                'reference_id' => is_array($data) ? ($data['reference_id'] ?? null) : null,
            ],
        );
    }

    public function validateConfiguration(array $config): bool
    {
        $accessToken = $config['access_token'] ?? null;
        $publicKey = $config['webhook_public_key'] ?? null;

        return is_string($accessToken)
            && $accessToken !== ''
            && is_string($publicKey)
            && $publicKey !== '';
    }

    public function verifyWebhookSignature(array $credentials, string $payload, string $signature, array $context = []): bool
    {
        $publicKey = $credentials['webhook_public_key'] ?? null;
        if (! is_string($publicKey) || $publicKey === '' || $signature === '') {
            return false;
        }

        $key = $this->publicKey($publicKey);
        if ($key === false) {
            return false;
        }

        foreach ($this->signatures($signature) as $candidate) {
            $decoded = base64_decode($candidate, true);
            if ($decoded !== false && openssl_verify($payload, $decoded, $key, OPENSSL_ALGO_SHA256) === 1) {
                return true;
            }
        }

        return false;
    }

    private function payLink(mixed $data): ?string
    {
        if (! is_array($data) || ! is_array($data['links'] ?? null)) {
            return null;
        }

        foreach ($data['links'] as $link) {
            if (! is_array($link) || ($link['rel'] ?? null) !== 'PAY' || ! is_string($link['href'] ?? null)) {
                continue;
            }

            if (filter_var($link['href'], FILTER_VALIDATE_URL) !== false) {
                return $link['href'];
            }
        }

        return null;
    }

    private function mapStatus(?string $status): PaymentChargeStatus
    {
        return match (strtoupper((string) $status)) {
            'PAID' => PaymentChargeStatus::Paid,
            'DECLINED', 'CANCELED', 'CANCELLED', 'EXPIRED' => PaymentChargeStatus::Failed,
            'AUTHORIZED', 'IN_ANALYSIS', 'WAITING', 'ACTIVE', 'INACTIVE' => PaymentChargeStatus::Pending,
            default => throw new UnexpectedValueException('O PagBank retornou um status de cobrança desconhecido.'),
        };
    }

    /** @return list<string> */
    private function signatures(string $signature): array
    {
        $trimmed = trim($signature);
        $decoded = json_decode($trimmed, true);

        if (is_array($decoded)) {
            return array_values(array_filter($decoded, is_string(...)));
        }

        return array_values(array_filter(array_map('trim', explode(',', $trimmed))));
    }

    private function publicKey(string $value): \OpenSSLAsymmetricKey|false
    {
        if (str_contains($value, 'BEGIN PUBLIC KEY')) {
            return openssl_pkey_get_public($value);
        }

        $encoded = preg_replace('/\s+/', '', $value);
        if (! is_string($encoded) || base64_decode($encoded, true) === false) {
            return false;
        }

        $pem = "-----BEGIN PUBLIC KEY-----\n".chunk_split($encoded, 64, "\n").'-----END PUBLIC KEY-----';

        return openssl_pkey_get_public($pem);
    }
}
