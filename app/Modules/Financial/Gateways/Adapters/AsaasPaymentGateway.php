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

class AsaasPaymentGateway implements PaymentGatewayInterface, PaymentGatewayWebhookInterface, PaymentGatewayWebhookStatusInterface
{
    private readonly string $baseUrl;

    public function __construct(
        private readonly Factory $http,
        ?string $baseUrl = null,
    ) {
        $this->baseUrl = rtrim($baseUrl ?? (string) config('services.asaas.base_url', 'https://api.asaas.com/v3'), '/');
    }

    public function identifier(): string
    {
        return 'asaas';
    }

    public function label(): string
    {
        return 'Asaas';
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
                    'label' => 'Chave de API Asaas',
                    'input' => 'password',
                    'required' => true,
                    'secret' => true,
                    'rules' => ['string', 'min:8'],
                ],
                'webhook_token' => [
                    'label' => 'Token do webhook Asaas',
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
            throw new InvalidArgumentException('Configuração do Asaas inválida.');
        }

        $description = $intent->description ?? 'Compra de curso';
        $response = $this->http
            ->withHeaders([
                'Accept' => 'application/json',
                'User-Agent' => 'Ead2026/1.0',
                'access_token' => $accessToken,
            ])
            ->withBody($this->checkoutPayload($intent, $description), 'application/json')
            ->post($this->baseUrl.'/checkouts');

        if (! $response->successful()) {
            throw new RuntimeException('O Asaas não respondeu com um checkout válido.');
        }

        $data = $response->json();
        $externalId = is_array($data) ? ($data['id'] ?? null) : null;
        $status = is_array($data) ? ($data['status'] ?? null) : null;
        $redirectUrl = is_array($data) ? ($data['link'] ?? null) : null;

        if (! is_string($externalId) || $externalId === '') {
            throw new UnexpectedValueException('O Asaas retornou um checkout inválido.');
        }

        if (! $this->isAsaasUrl($redirectUrl)) {
            $redirectUrl = 'https://asaas.com/checkoutSession/show?id='.rawurlencode($externalId);
        }

        return new ChargeResult(
            status: $this->mapStatus(is_string($status) ? $status : null),
            externalId: $externalId,
            redirectUrl: $redirectUrl,
            raw: [
                'id' => $externalId,
                'status' => $status,
                'external_reference' => is_array($data) ? ($data['externalReference'] ?? null) : null,
            ],
        );
    }

    public function validateConfiguration(array $config): bool
    {
        $accessToken = $config['access_token'] ?? null;
        $webhookToken = $config['webhook_token'] ?? null;

        return is_string($accessToken)
            && $accessToken !== ''
            && is_string($webhookToken)
            && strlen($webhookToken) >= 32
            && trim($webhookToken) === $webhookToken;
    }

    public function resolveWebhookStatus(array $credentials, string $externalId): PaymentChargeStatus
    {
        $accessToken = $credentials['access_token'] ?? null;

        if (! is_string($accessToken) || $accessToken === '' || $externalId === '') {
            throw new InvalidArgumentException('Configuração do Asaas inválida.');
        }

        $response = $this->http
            ->withHeaders([
                'Accept' => 'application/json',
                'User-Agent' => 'Ead2026/1.0',
                'access_token' => $accessToken,
            ])
            ->get($this->baseUrl.'/payments', [
                'checkoutSession' => $externalId,
                'limit' => 1,
            ]);

        if (! $response->successful()) {
            throw new RuntimeException('O Asaas não respondeu com o estado da cobrança.');
        }

        $data = $response->json();
        $payments = is_array($data) && is_array($data['data'] ?? null) ? $data['data'] : [];

        if ($payments === []) {
            return PaymentChargeStatus::Pending;
        }

        $providerStatus = $payments[0]['status'] ?? null;

        if (! is_string($providerStatus) || $providerStatus === '') {
            throw new UnexpectedValueException('O Asaas retornou um estado de cobrança inválido.');
        }

        return $this->mapPaymentStatus($providerStatus);
    }

    /** @param  array<string, mixed>  $context */
    public function verifyWebhookSignature(array $credentials, string $payload, string $signature, array $context = []): bool
    {
        $webhookToken = $credentials['webhook_token'] ?? null;

        return is_string($webhookToken)
            && $webhookToken !== ''
            && $signature !== ''
            && hash_equals($webhookToken, $signature);
    }

    private function checkoutPayload(ChargeIntent $intent, string $description): string
    {
        return '{'
            .'"billingTypes":["PIX"],'
            .'"chargeTypes":["DETACHED"],'
            .'"minutesToExpire":1440,'
            .'"externalReference":'.$this->json($intent->reference).','
            .'"callback":{'
                .'"successUrl":'.$this->json((string) config('app.url')).','
                .'"cancelUrl":'.$this->json((string) config('app.url')).','
                .'"expiredUrl":'.$this->json((string) config('app.url'))
            .'},'
            .'"items":[{'
                .'"externalReference":'.$this->json($intent->reference).','
                .'"name":'.$this->json($description).','
                .'"description":'.$this->json($description).','
                .'"quantity":1,'
                .'"value":'.$this->decimalAmount($intent->amountCents)
            .'}]'
        .'}';
    }

    private function decimalAmount(int $amountCents): string
    {
        if ($amountCents <= 0) {
            throw new InvalidArgumentException('O valor da cobrança deve ser positivo.');
        }

        return intdiv($amountCents, 100).'.'.str_pad((string) ($amountCents % 100), 2, '0', STR_PAD_LEFT);
    }

    private function json(mixed $value): string
    {
        return json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
    }

    private function isAsaasUrl(mixed $url): bool
    {
        if (! is_string($url) || filter_var($url, FILTER_VALIDATE_URL) === false) {
            return false;
        }

        $host = parse_url($url, PHP_URL_HOST);

        return is_string($host)
            && ($host === 'asaas.com' || str_ends_with($host, '.asaas.com'));
    }

    private function mapStatus(?string $status): PaymentChargeStatus
    {
        return match (strtoupper((string) $status)) {
            'PAID' => PaymentChargeStatus::Paid,
            'CANCELED', 'CANCELLED', 'EXPIRED', 'FAILED' => PaymentChargeStatus::Failed,
            'ACTIVE', 'PENDING', 'CREATED' => PaymentChargeStatus::Pending,
            default => throw new UnexpectedValueException('O Asaas retornou um status de cobrança desconhecido.'),
        };
    }

    private function mapPaymentStatus(string $status): PaymentChargeStatus
    {
        return match (strtoupper($status)) {
            'RECEIVED', 'CONFIRMED', 'RECEIVED_IN_CASH' => PaymentChargeStatus::Paid,
            'REFUNDED', 'REFUND_REQUESTED', 'CHARGEBACK_REQUESTED', 'CHARGEBACK_DISPUTE' => PaymentChargeStatus::Failed,
            'PENDING', 'OVERDUE', 'DUNNING_REQUESTED', 'DUNNING_RECEIVED', 'AWAITING_RISK_ANALYSIS', 'AWAITING_CHARGEBACK_REVERSAL' => PaymentChargeStatus::Pending,
            default => throw new UnexpectedValueException('O Asaas retornou um estado de cobrança desconhecido.'),
        };
    }
}
