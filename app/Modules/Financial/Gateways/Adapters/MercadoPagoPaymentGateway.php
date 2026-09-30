<?php

namespace App\Modules\Financial\Gateways\Adapters;

use App\Modules\Financial\Contracts\GatewayConfigurationDefinition;
use App\Modules\Financial\Enums\PaymentChargeStatus;
use App\Modules\Financial\Enums\PaymentConfirmationMode;
use App\Modules\Financial\Gateways\Contracts\PaymentGatewayInterface;
use App\Modules\Financial\Gateways\Contracts\PaymentGatewayWebhookInterface;
use App\Modules\Financial\Gateways\Contracts\PaymentGatewayWebhookStatusInterface;
use App\Modules\Financial\Gateways\Data\ChargeIntent;
use App\Modules\Financial\Gateways\Data\ChargeResult;
use Illuminate\Http\Client\Factory;
use InvalidArgumentException;
use RuntimeException;
use UnexpectedValueException;

class MercadoPagoPaymentGateway implements PaymentGatewayInterface, PaymentGatewayWebhookInterface, PaymentGatewayWebhookStatusInterface
{
    private const WEBHOOK_TOLERANCE_MILLISECONDS = 300000;

    private readonly string $baseUrl;

    public function __construct(
        private readonly Factory $http,
        ?string $baseUrl = null,
    ) {
        $this->baseUrl = rtrim($baseUrl ?? (string) config('services.mercadopago.base_url', 'https://api.mercadopago.com'), '/');
    }

    public function identifier(): string
    {
        return 'mercadopago';
    }

    public function label(): string
    {
        return 'Mercado Pago';
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
                    'label' => 'Access Token Mercado Pago',
                    'input' => 'password',
                    'required' => true,
                    'secret' => true,
                    'rules' => ['string', 'min:8'],
                ],
                'webhook_secret' => [
                    'label' => 'Segredo do webhook Mercado Pago',
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
        $accessToken = $credentials['access_token'] ?? null;

        if (! is_string($accessToken) || $accessToken === '') {
            throw new InvalidArgumentException('Configuração do Mercado Pago inválida.');
        }

        $amount = $this->decimalAmount($intent->amountCents);
        $description = $intent->description ?? 'Compra de curso';

        $response = $this->http
            ->asJson()
            ->withToken($accessToken)
            ->withHeader('X-Idempotency-Key', $intent->idempotencyKey)
            ->post($this->baseUrl.'/v1/orders', [
                'type' => 'online',
                'processing_mode' => 'manual',
                'capture_mode' => 'automatic_async',
                'total_amount' => $amount,
                'external_reference' => $intent->reference,
                'description' => $description,
                'items' => [[
                    'title' => $description,
                    'unit_price' => $amount,
                    'quantity' => 1,
                    'unit_measure' => 'unit',
                    'total_amount' => $amount,
                ]],
            ]);

        if (! $response->successful()) {
            throw new RuntimeException('O Mercado Pago não respondeu com uma cobrança válida.');
        }

        $data = $response->json();
        $externalId = is_array($data) ? ($data['id'] ?? null) : null;
        $status = is_array($data) ? ($data['status'] ?? null) : null;
        $checkoutUrl = is_array($data) ? ($data['checkout_url'] ?? null) : null;

        if (! is_string($externalId) || $externalId === '' || ! is_string($status) || $status === '') {
            throw new UnexpectedValueException('O Mercado Pago retornou uma cobrança inválida.');
        }

        if (! is_string($checkoutUrl) || filter_var($checkoutUrl, FILTER_VALIDATE_URL) === false) {
            throw new UnexpectedValueException('O Mercado Pago não retornou uma URL de checkout válida.');
        }

        $chargeStatus = $this->mapStatus($status);

        return new ChargeResult(
            status: $chargeStatus,
            externalId: $externalId,
            redirectUrl: $checkoutUrl,
            raw: [
                'id' => $externalId,
                'status' => $status,
                'status_detail' => is_array($data) ? ($data['status_detail'] ?? null) : null,
                'total_amount' => is_array($data) ? ($data['total_amount'] ?? null) : null,
            ],
        );
    }

    public function resolveWebhookStatus(array $credentials, string $externalId): PaymentChargeStatus
    {
        $accessToken = $credentials['access_token'] ?? null;

        if (! is_string($accessToken) || $accessToken === '' || $externalId === '') {
            throw new InvalidArgumentException('Configuração do Mercado Pago inválida.');
        }

        $response = $this->http
            ->withToken($accessToken)
            ->get($this->baseUrl.'/v1/orders/'.rawurlencode($externalId));

        if (! $response->successful()) {
            throw new RuntimeException('O Mercado Pago não respondeu com o estado da cobrança.');
        }

        $data = $response->json();
        $status = is_array($data) ? ($data['status'] ?? null) : null;

        if (! is_string($status) || $status === '') {
            throw new UnexpectedValueException('O Mercado Pago retornou um estado de cobrança inválido.');
        }

        return $this->mapStatus($status);
    }

    public function validateConfiguration(array $config): bool
    {
        $accessToken = $config['access_token'] ?? null;
        $webhookSecret = $config['webhook_secret'] ?? null;

        return is_string($accessToken)
            && $accessToken !== ''
            && is_string($webhookSecret)
            && $webhookSecret !== '';
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public function verifyWebhookSignature(array $credentials, string $payload, string $signature, array $context = []): bool
    {
        $secret = $credentials['webhook_secret'] ?? null;
        $dataId = $context['data_id'] ?? null;
        $requestId = $context['request_id'] ?? null;

        if (! is_string($secret) || $secret === '' || ! is_string($dataId) || $dataId === '' || ! is_string($requestId) || $requestId === '') {
            return false;
        }

        $timestamp = null;
        $signatures = [];

        foreach (explode(',', $signature) as $part) {
            [$key, $value] = array_pad(explode('=', trim($part), 2), 2, null);

            if ($key === 'ts' && is_string($value) && ctype_digit($value)) {
                $timestamp = (int) $value;
            }

            if ($key === 'v1' && is_string($value) && preg_match('/\A[a-f0-9]{64}\z/i', $value) === 1) {
                $signatures[] = strtolower($value);
            }
        }

        $nowMilliseconds = (int) floor(microtime(true) * 1000);

        if ($timestamp === null || $signatures === [] || abs($nowMilliseconds - $timestamp) > self::WEBHOOK_TOLERANCE_MILLISECONDS) {
            return false;
        }

        $manifest = 'id:'.strtolower($dataId).';request-id:'.$requestId.';ts:'.$timestamp.';';
        $expected = hash_hmac('sha256', $manifest, $secret);

        foreach ($signatures as $provided) {
            if (hash_equals($expected, $provided)) {
                return true;
            }
        }

        return false;
    }

    private function decimalAmount(int $amountCents): string
    {
        if ($amountCents <= 0) {
            throw new InvalidArgumentException('O valor da cobrança deve ser positivo.');
        }

        return intdiv($amountCents, 100).'.'.str_pad((string) ($amountCents % 100), 2, '0', STR_PAD_LEFT);
    }

    private function mapStatus(string $status): PaymentChargeStatus
    {
        return match ($status) {
            'processed' => PaymentChargeStatus::Paid,
            'failed', 'canceled', 'expired', 'refunded' => PaymentChargeStatus::Failed,
            'created', 'processing', 'action_required' => PaymentChargeStatus::Pending,
            default => throw new UnexpectedValueException('O Mercado Pago retornou um status de cobrança desconhecido.'),
        };
    }
}
