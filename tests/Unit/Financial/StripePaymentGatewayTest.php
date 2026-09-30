<?php

use App\Modules\Financial\Enums\PaymentChargeStatus;
use App\Modules\Financial\Gateways\Adapters\StripePaymentGateway;
use App\Modules\Financial\Gateways\Data\ChargeIntent;
use App\Modules\Financial\Gateways\PaymentGatewayManager;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Http;

uses(Tests\TestCase::class);

function stripeGatewayForTest(): StripePaymentGateway
{
    return new StripePaymentGateway(app(Factory::class), 'https://stripe.test/v1');
}

function stripeIntentForTest(): ChargeIntent
{
    return new ChargeIntent(
        amountCents: 4990,
        currency: 'brl',
        reference: 'ORD-STRIPE-123',
        idempotencyKey: 'stripe-idempotency-key',
        description: 'Compra de curso',
        metadata: ['order_id' => 42],
    );
}

it('registers Stripe as an automatic gateway with a secret configuration schema', function (): void {
    $gateway = app(PaymentGatewayManager::class)->get('stripe');

    expect($gateway)->toBeInstanceOf(StripePaymentGateway::class)
        ->and($gateway?->identifier())->toBe('stripe')
        ->and($gateway?->configurationSchema()->fields)->toMatchArray([
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
        ]);
});

it('validates Stripe secret and webhook key identities', function (): void {
    $gateway = stripeGatewayForTest();

    expect($gateway->validateConfiguration([
        'secret_key' => 'sk_test_123',
        'webhook_secret' => 'whsec_123',
    ]))->toBeTrue()
        ->and($gateway->validateConfiguration([
            'secret_key' => 'not-a-stripe-key',
            'webhook_secret' => 'whsec_123',
        ]))->toBeFalse()
        ->and($gateway->validateConfiguration([
            'secret_key' => 'sk_test_123',
            'webhook_secret' => 'not-a-webhook-key',
        ]))->toBeFalse();
});

it('creates a Stripe PaymentIntent with cents, idempotency and safe normalized raw data', function (): void {
    Http::fake([
        'https://stripe.test/v1/payment_intents' => Http::response([
            'id' => 'pi_123',
            'status' => 'requires_action',
            'client_secret' => 'pi_123_secret_456',
            'amount' => 4990,
            'currency' => 'brl',
            'metadata' => ['reference' => 'ORD-STRIPE-123'],
        ]),
    ]);

    $result = stripeGatewayForTest()->charge([
        'secret_key' => 'sk_test_123',
    ], stripeIntentForTest());

    Http::assertSent(function ($request): bool {
        $authorization = $request->header('Authorization')[0] ?? '';

        return $request->method() === 'POST'
            && $request->url() === 'https://stripe.test/v1/payment_intents'
            && $request->hasHeader('Idempotency-Key', 'stripe-idempotency-key')
            && base64_decode(str_replace('Basic ', '', $authorization), true) === 'sk_test_123:'
            && $request->data()['amount'] === 4990
            && $request->data()['currency'] === 'brl'
            && $request->data()['automatic_payment_methods[enabled]'] === 'true'
            && $request->data()['metadata[reference]'] === 'ORD-STRIPE-123'
            && $request->data()['metadata[order_id]'] === '42';
    });

    expect($result->status)->toBe(PaymentChargeStatus::Pending)
        ->and($result->externalId)->toBe('pi_123')
        ->and($result->clientSecret)->toBe('pi_123_secret_456')
        ->and($result->raw)->toBe([
            'id' => 'pi_123',
            'status' => 'requires_action',
            'amount' => 4990,
            'currency' => 'brl',
        ])
        ->and($result->raw)->not->toHaveKey('client_secret');
});

it('normalizes successful and canceled PaymentIntents', function (string $stripeStatus, PaymentChargeStatus $expectedStatus): void {
    Http::fake([
        '*' => Http::response([
            'id' => 'pi_123',
            'status' => $stripeStatus,
            'client_secret' => 'pi_123_secret_456',
        ]),
    ]);

    $result = stripeGatewayForTest()->charge(['secret_key' => 'sk_test_123'], stripeIntentForTest());

    expect($result->status)->toBe($expectedStatus);
})->with([
    ['succeeded', PaymentChargeStatus::Paid],
    ['canceled', PaymentChargeStatus::Failed],
]);

it('rejects unsuccessful or malformed Stripe responses without persisting provider payloads', function (): void {
    Http::fakeSequence('*')
        ->push(['error' => ['message' => 'secret provider detail']], 402)
        ->push(['id' => 'pi_123', 'status' => 'unknown', 'client_secret' => 'pi_secret']);

    expect(fn (): mixed => stripeGatewayForTest()->charge(['secret_key' => 'sk_test_123'], stripeIntentForTest()))
        ->toThrow(RuntimeException::class, 'A Stripe não respondeu com uma cobrança válida.');

    expect(fn (): mixed => stripeGatewayForTest()->charge(['secret_key' => 'sk_test_123'], stripeIntentForTest()))
        ->toThrow(\UnexpectedValueException::class, 'A Stripe retornou um status de cobrança desconhecido.');
});

it('verifies Stripe webhook signatures against the raw body and rejects replayed or malformed signatures', function (): void {
    $gateway = stripeGatewayForTest();
    $payload = '{"id":"evt_123","type":"payment_intent.succeeded"}';
    $secret = 'whsec_123';
    $timestamp = now()->timestamp;
    $digest = hash_hmac('sha256', $timestamp.'.'.$payload, $secret);

    expect($gateway->verifyWebhookSignature(
        ['webhook_secret' => $secret],
        $payload,
        't='.$timestamp.',v1='.$digest,
    ))->toBeTrue()
        ->and($gateway->verifyWebhookSignature(
            ['webhook_secret' => $secret],
            $payload,
            't='.($timestamp - 301).',v1='.$digest,
        ))->toBeFalse()
        ->and($gateway->verifyWebhookSignature(
            ['webhook_secret' => $secret],
            $payload,
            'not-a-stripe-signature',
        ))->toBeFalse();
});
