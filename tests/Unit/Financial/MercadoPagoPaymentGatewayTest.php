<?php

use App\Modules\Financial\Enums\PaymentChargeStatus;
use App\Modules\Financial\Gateways\Adapters\MercadoPagoPaymentGateway;
use App\Modules\Financial\Gateways\Data\ChargeIntent;
use App\Modules\Financial\Gateways\PaymentGatewayManager;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Http;

uses(Tests\TestCase::class);

function mercadoPagoGatewayForTest(): MercadoPagoPaymentGateway
{
    return new MercadoPagoPaymentGateway(app(Factory::class), 'https://mercadopago.test');
}

function mercadoPagoIntentForTest(): ChargeIntent
{
    return new ChargeIntent(
        amountCents: 4990,
        currency: 'brl',
        reference: 'ORD-MP-123',
        idempotencyKey: 'mp-idempotency-key',
        description: 'Compra de curso',
    );
}

it('registers Mercado Pago as an automatic redirect gateway', function (): void {
    $gateway = app(PaymentGatewayManager::class)->get('mercadopago');

    expect($gateway)->toBeInstanceOf(MercadoPagoPaymentGateway::class)
        ->and($gateway?->identifier())->toBe('mercadopago')
        ->and($gateway?->configurationSchema()->fields)->toHaveKeys(['access_token', 'webhook_secret']);
});

it('creates a Mercado Pago Checkout Pro order using decimal strings and idempotency', function (): void {
    Http::fake([
        'https://mercadopago.test/v1/orders' => Http::response([
            'id' => 'ORDTST123',
            'status' => 'created',
            'status_detail' => 'created',
            'total_amount' => '49.90',
            'checkout_url' => 'https://www.mercadopago.com.br/checkout/v1/redirect?order_id=ORDTST123',
        ], 201),
    ]);

    $result = mercadoPagoGatewayForTest()->charge([
        'access_token' => 'APP_USR-test-token',
    ], mercadoPagoIntentForTest());

    Http::assertSent(function ($request): bool {
        $data = $request->data();

        return $request->method() === 'POST'
            && $request->url() === 'https://mercadopago.test/v1/orders'
            && $request->hasHeader('X-Idempotency-Key', 'mp-idempotency-key')
            && $request->header('Authorization')[0] === 'Bearer APP_USR-test-token'
            && $data['total_amount'] === '49.90'
            && $data['external_reference'] === 'ORD-MP-123'
            && $data['items'][0]['unit_price'] === '49.90'
            && $data['items'][0]['total_amount'] === '49.90'
            && $data['items'][0]['quantity'] === 1;
    });

    expect($result->status)->toBe(PaymentChargeStatus::Pending)
        ->and($result->externalId)->toBe('ORDTST123')
        ->and($result->redirectUrl)->toBe('https://www.mercadopago.com.br/checkout/v1/redirect?order_id=ORDTST123')
        ->and($result->raw)->toBe([
            'id' => 'ORDTST123',
            'status' => 'created',
            'status_detail' => 'created',
            'total_amount' => '49.90',
        ]);
});

it('normalizes Mercado Pago order statuses', function (string $providerStatus, PaymentChargeStatus $expectedStatus): void {
    Http::fake([
        '*' => Http::response([
            'id' => 'ORDTST123',
            'status' => $providerStatus,
            'checkout_url' => 'https://www.mercadopago.com.br/checkout/v1/redirect?order_id=ORDTST123',
        ], 201),
    ]);

    expect(mercadoPagoGatewayForTest()->charge(['access_token' => 'APP_USR-test-token'], mercadoPagoIntentForTest())->status)
        ->toBe($expectedStatus);
})->with([
    ['processed', PaymentChargeStatus::Paid],
    ['failed', PaymentChargeStatus::Failed],
    ['created', PaymentChargeStatus::Pending],
]);

it('resolves the authoritative order status for a native webhook', function (): void {
    Http::fake([
        'https://mercadopago.test/v1/orders/ORDTST123' => Http::response([
            'id' => 'ORDTST123',
            'status' => 'processed',
        ]),
    ]);

    expect(mercadoPagoGatewayForTest()->resolveWebhookStatus([
        'access_token' => 'APP_USR-test-token',
    ], 'ORDTST123'))->toBe(PaymentChargeStatus::Paid);

    Http::assertSent(function ($request): bool {
        return $request->method() === 'GET'
            && $request->url() === 'https://mercadopago.test/v1/orders/ORDTST123'
            && $request->header('Authorization')[0] === 'Bearer APP_USR-test-token';
    });
});

it('verifies Mercado Pago x-signature using request context and rejects replay or missing context', function (): void {
    $gateway = mercadoPagoGatewayForTest();
    $secret = 'mercadopago-webhook-secret';
    $dataId = 'ORDTST123';
    $requestId = 'request-123';
    $timestamp = (int) floor(microtime(true) * 1000);
    $manifest = 'id:'.strtolower($dataId).';request-id:'.$requestId.';ts:'.$timestamp.';';
    $digest = hash_hmac('sha256', $manifest, $secret);

    expect($gateway->verifyWebhookSignature(
        ['webhook_secret' => $secret],
        '{}',
        'ts='.$timestamp.',v1='.$digest,
        ['data_id' => $dataId, 'request_id' => $requestId],
    ))->toBeTrue()
        ->and($gateway->verifyWebhookSignature(
            ['webhook_secret' => $secret],
            '{}',
            'ts='.($timestamp - 300001).',v1='.$digest,
            ['data_id' => $dataId, 'request_id' => $requestId],
        ))->toBeFalse()
        ->and($gateway->verifyWebhookSignature(
            ['webhook_secret' => $secret],
            '{}',
            'ts='.$timestamp.',v1='.$digest,
        ))->toBeFalse();
});
