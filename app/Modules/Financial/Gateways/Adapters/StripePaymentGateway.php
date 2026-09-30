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

class StripePaymentGateway implements PaymentGatewayInterface, PaymentGatewayWebhookInterface
{
    private const WEBHOOK_TOLERANCE_SECONDS = 300;

    private readonly string $baseUrl;

    public function __construct(
        private readonly Factory $http,
        ?string $baseUrl = null,
    ) {
        $this->baseUrl = rtrim($baseUrl ?? (string) config('services.stripe.base_url', 'https://api.stripe.com/v1'), '/');
    }

    public function identifier(): string
    {
        return 'stripe';
    }

    public function label(): string
    {
        return 'Stripe';
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
                'secret_key' => [
                    'label' => 'Chave secreta Stripe',
                    'input' => 'password',
                    'required' => true,
                    'secret' => true,
                    'rules' => ['string', 'min:8'],
                ],
                'webhook_secret' => [
                    'label' => 'Segredo do webhook Stripe',
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
        $secretKey = $credentials['secret_key'] ?? null;

        if (! is_string($secretKey) || $secretKey === '') {
            throw new InvalidArgumentException('Configuração Stripe inválida.');
        }

        $parameters = [
            'amount' => $intent->amountCents,
            'currency' => strtolower($intent->currency),
            'automatic_payment_methods[enabled]' => 'true',
            'metadata[reference]' => $intent->reference,
        ];

        if ($intent->description !== null) {
            $parameters['description'] = $intent->description;
        }

        foreach ($intent->metadata as $key => $value) {
            if (is_scalar($value)) {
                $parameters['metadata['.$key.']'] = (string) $value;
            }
        }

        $response = $this->http
            ->asForm()
            ->withBasicAuth($secretKey, '')
            ->withHeader('Idempotency-Key', $intent->idempotencyKey)
            ->post($this->baseUrl.'/payment_intents', $parameters);

        if (! $response->successful()) {
            throw new RuntimeException('A Stripe não respondeu com uma cobrança válida.');
        }

        $data = $response->json();
        $externalId = is_array($data) ? ($data['id'] ?? null) : null;
        $status = is_array($data) ? ($data['status'] ?? null) : null;
        $clientSecret = is_array($data) ? ($data['client_secret'] ?? null) : null;

        if (! is_string($externalId) || $externalId === '' || ! is_string($status) || $status === '') {
            throw new UnexpectedValueException('A Stripe retornou uma cobrança inválida.');
        }

        if (! is_string($clientSecret) || $clientSecret === '') {
            throw new UnexpectedValueException('A Stripe não retornou o segredo de cliente da cobrança.');
        }

        $chargeStatus = match ($status) {
            'succeeded' => PaymentChargeStatus::Paid,
            'canceled' => PaymentChargeStatus::Failed,
            'requires_payment_method',
            'requires_confirmation',
            'requires_action',
            'requires_capture',
            'processing' => PaymentChargeStatus::Pending,
            default => throw new UnexpectedValueException('A Stripe retornou um status de cobrança desconhecido.'),
        };

        return new ChargeResult(
            status: $chargeStatus,
            externalId: $externalId,
            clientSecret: $clientSecret,
            raw: [
                'id' => $externalId,
                'status' => $status,
                'amount' => is_array($data) ? ($data['amount'] ?? null) : null,
                'currency' => is_array($data) ? ($data['currency'] ?? null) : null,
            ],
        );
    }

    public function validateConfiguration(array $config): bool
    {
        $secretKey = $config['secret_key'] ?? null;
        $webhookSecret = $config['webhook_secret'] ?? null;

        return is_string($secretKey)
            && preg_match('/\A(?:sk|rk)_(?:test|live)_[^\s]+\z/', $secretKey) === 1
            && is_string($webhookSecret)
            && preg_match('/\Awhsec_[^\s]+\z/', $webhookSecret) === 1;
    }

    /** @param  array<string, mixed>  $context */
    public function verifyWebhookSignature(array $credentials, string $payload, string $signature, array $context = []): bool
    {
        $webhookSecret = $credentials['webhook_secret'] ?? null;

        if (! is_string($webhookSecret) || $webhookSecret === '') {
            return false;
        }

        $timestamp = null;
        $signatures = [];

        foreach (explode(',', $signature) as $part) {
            [$key, $value] = array_pad(explode('=', trim($part), 2), 2, null);

            if ($key === 't' && is_string($value) && ctype_digit($value)) {
                $timestamp = (int) $value;
            }

            if ($key === 'v1' && is_string($value) && preg_match('/\A[a-f0-9]{64}\z/i', $value) === 1) {
                $signatures[] = strtolower($value);
            }
        }

        if ($timestamp === null || $signatures === [] || abs(now()->timestamp - $timestamp) > self::WEBHOOK_TOLERANCE_SECONDS) {
            return false;
        }

        $expected = hash_hmac('sha256', $timestamp.'.'.$payload, $webhookSecret);

        foreach ($signatures as $provided) {
            if (hash_equals($expected, $provided)) {
                return true;
            }
        }

        return false;
    }
}
