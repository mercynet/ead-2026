<?php

use App\Modules\Financial\Enums\PaymentChargeStatus;
use App\Modules\Financial\Gateways\Adapters\AsaasPaymentGateway;
use App\Modules\Financial\Gateways\Data\ChargeIntent;
use App\Modules\Financial\Gateways\PaymentGatewayManager;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Http;

uses(Tests\TestCase::class);

function asaasGatewayForTest(): AsaasPaymentGateway
{
    return new AsaasPaymentGateway(app(Factory::class), 'https://asaas.test/v3');
}

function asaasIntentForTest(): ChargeIntent
{
    return new ChargeIntent(
        amountCents: 4990,
        currency: 'brl',
        reference: 'ORD-ASAAS-123',
        idempotencyKey: 'asaas-idempotency-key',
        description: 'Compra de curso',
    );
}

it('registers Asaas as an automatic Pix redirect gateway', function (): void {
    $gateway = app(PaymentGatewayManager::class)->get('asaas');

    expect($gateway)->toBeInstanceOf(AsaasPaymentGateway::class)
        ->and($gateway?->identifier())->toBe('asaas')
        ->and($gateway?->configurationSchema()->fields)->toHaveKeys(['access_token', 'webhook_token']);
});

it('creates an Asaas Pix checkout with a decimal JSON amount and safe response', function (): void {
    Http::fake([
        'https://asaas.test/v3/checkouts' => Http::response([
            'id' => 'checkout-asaas-123',
            'status' => 'ACTIVE',
            'externalReference' => 'ORD-ASAAS-123',
            'link' => 'https://asaas.com/checkoutSession/show?id=checkout-asaas-123',
        ], 200),
    ]);

    $result = asaasGatewayForTest()->charge([
        'access_token' => '$aact_hmlg_test-token',
    ], asaasIntentForTest());

    Http::assertSent(function ($request): bool {
        $body = $request->body();

        return $request->method() === 'POST'
            && $request->url() === 'https://asaas.test/v3/checkouts'
            && $request->header('access_token')[0] === '$aact_hmlg_test-token'
            && $request->header('User-Agent')[0] === 'Ead2026/1.0'
            && str_contains($body, '"billingTypes":["PIX"]')
            && str_contains($body, '"chargeTypes":["DETACHED"]')
            && str_contains($body, '"externalReference":"ORD-ASAAS-123"')
            && str_contains($body, '"value":49.90');
    });

    expect($result->status)->toBe(PaymentChargeStatus::Pending)
        ->and($result->externalId)->toBe('checkout-asaas-123')
        ->and($result->redirectUrl)->toBe('https://asaas.com/checkoutSession/show?id=checkout-asaas-123')
        ->and($result->raw)->toBe([
            'id' => 'checkout-asaas-123',
            'status' => 'ACTIVE',
            'external_reference' => 'ORD-ASAAS-123',
        ]);
});

it('normalizes Asaas checkout statuses', function (string $providerStatus, PaymentChargeStatus $expectedStatus): void {
    Http::fake([
        '*' => Http::response([
            'id' => 'checkout-asaas-123',
            'status' => $providerStatus,
            'link' => 'https://asaas.com/checkoutSession/show?id=checkout-asaas-123',
        ], 200),
    ]);

    expect(asaasGatewayForTest()->charge(['access_token' => '$aact_hmlg_test-token'], asaasIntentForTest())->status)
        ->toBe($expectedStatus);
})->with([
    ['ACTIVE', PaymentChargeStatus::Pending],
    ['PAID', PaymentChargeStatus::Paid],
    ['EXPIRED', PaymentChargeStatus::Failed],
]);

it('does not expose an untrusted checkout link from the provider response', function (): void {
    Http::fake([
        'https://asaas.test/v3/checkouts' => Http::response([
            'id' => 'checkout-asaas-123',
            'status' => 'ACTIVE',
            'link' => 'https://evil.example/collect-token',
        ], 200),
    ]);

    expect(asaasGatewayForTest()->charge(['access_token' => '$aact_hmlg_test-token'], asaasIntentForTest())->redirectUrl)
        ->toBe('https://asaas.com/checkoutSession/show?id=checkout-asaas-123');
});

it('resolves the authoritative payment status by checkout session', function (): void {
    Http::fake([
        'https://asaas.test/v3/payments*' => Http::response([
            'data' => [[
                'id' => 'pay-asaas-123',
                'status' => 'RECEIVED',
            ]],
        ], 200),
    ]);

    expect(asaasGatewayForTest()->resolveWebhookStatus([
        'access_token' => '$aact_hmlg_test-token',
    ], 'checkout-asaas-123'))->toBe(PaymentChargeStatus::Paid);

    Http::assertSent(function ($request): bool {
        return $request->method() === 'GET'
            && $request->url() === 'https://asaas.test/v3/payments?checkoutSession=checkout-asaas-123&limit=1'
            && $request->header('access_token')[0] === '$aact_hmlg_test-token'
            && $request->header('User-Agent')[0] === 'Ead2026/1.0';
    });
});

it('keeps an Asaas webhook synchronization pending until a payment is available', function (): void {
    Http::fake([
        'https://asaas.test/v3/payments*' => Http::response(['data' => []], 200),
    ]);

    expect(asaasGatewayForTest()->resolveWebhookStatus([
        'access_token' => '$aact_hmlg_test-token',
    ], 'checkout-asaas-123'))->toBe(PaymentChargeStatus::Pending);
});

it('verifies the Asaas webhook access token without treating it as a bearer credential', function (): void {
    $token = str_repeat('webhook-token-', 3);

    expect(asaasGatewayForTest()->verifyWebhookSignature(
        ['webhook_token' => $token],
        '{"event":"CHECKOUT_PAID"}',
        $token,
    ))->toBeTrue()
        ->and(asaasGatewayForTest()->verifyWebhookSignature(
            ['webhook_token' => $token],
            '{"event":"CHECKOUT_PAID"}',
            'wrong-token',
        ))->toBeFalse();
});
