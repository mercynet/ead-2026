<?php

use App\Modules\Financial\Enums\PaymentChargeStatus;
use App\Modules\Financial\Gateways\Adapters\PagSeguroPaymentGateway;
use App\Modules\Financial\Gateways\Data\ChargeIntent;
use App\Modules\Financial\Gateways\PaymentGatewayManager;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Http;

uses(Tests\TestCase::class);

function pagSeguroGatewayForTest(): PagSeguroPaymentGateway
{
    return new PagSeguroPaymentGateway(app(Factory::class), 'https://pagseguro.test');
}

function pagSeguroIntentForTest(): ChargeIntent
{
    return new ChargeIntent(
        amountCents: 4990,
        currency: 'brl',
        reference: 'ORD-PAGSEGURO-123',
        idempotencyKey: 'pagseguro-idempotency-key',
        description: 'Compra de curso',
    );
}

/** @return array{private: string, public: string} */
function pagSeguroSigningKeysForTest(): array
{
    $privateKey = openssl_pkey_new([
        'private_key_type' => OPENSSL_KEYTYPE_EC,
        'curve_name' => 'prime256v1',
    ]);
    if ($privateKey === false || ! openssl_pkey_export($privateKey, $privatePem)) {
        throw new RuntimeException('Não foi possível gerar a chave de teste.');
    }

    $details = openssl_pkey_get_details($privateKey);
    if (! is_array($details) || ! is_string($details['key'] ?? null)) {
        throw new RuntimeException('Não foi possível extrair a chave pública de teste.');
    }

    return ['private' => $privatePem, 'public' => $details['key']];
}

it('registers PagSeguro as an automatic redirect gateway', function (): void {
    $gateway = app(PaymentGatewayManager::class)->get('pagseguro');

    expect($gateway)->toBeInstanceOf(PagSeguroPaymentGateway::class)
        ->and($gateway?->identifier())->toBe('pagseguro')
        ->and($gateway?->configurationSchema()->fields)->toHaveKeys(['access_token', 'webhook_public_key']);
});

it('creates a PagBank checkout with cents, idempotency and the PAY link', function (): void {
    Http::fake([
        'https://pagseguro.test/checkouts' => Http::response([
            'id' => 'CHEC_TEST-123',
            'status' => 'ACTIVE',
            'reference_id' => 'ORD-PAGSEGURO-123',
            'links' => [[
                'rel' => 'PAY',
                'href' => 'https://pagamento.pagbank.com.br/pagamento?code=TEST123',
            ]],
        ], 200),
    ]);

    $result = pagSeguroGatewayForTest()->charge([
        'access_token' => 'pagbank-access-token',
    ], pagSeguroIntentForTest());

    Http::assertSent(function ($request): bool {
        $data = $request->data();

        return $request->method() === 'POST'
            && $request->url() === 'https://pagseguro.test/checkouts'
            && $request->hasHeader('x-idempotency-key', 'pagseguro-idempotency-key')
            && $request->header('Authorization')[0] === 'Bearer pagbank-access-token'
            && $data['reference_id'] === 'ORD-PAGSEGURO-123'
            && $data['items'][0]['unit_amount'] === 4990
            && $data['items'][0]['quantity'] === 1;
    });

    expect($result->status)->toBe(PaymentChargeStatus::Pending)
        ->and($result->externalId)->toBe('CHEC_TEST-123')
        ->and($result->redirectUrl)->toBe('https://pagamento.pagbank.com.br/pagamento?code=TEST123')
        ->and($result->raw)->toBe([
            'id' => 'CHEC_TEST-123',
            'status' => 'ACTIVE',
            'reference_id' => 'ORD-PAGSEGURO-123',
        ]);
});

it('verifies PagBank x-payload-signature with the configured public key', function (): void {
    $keys = pagSeguroSigningKeysForTest();
    $payload = '{"id":"CHEC_TEST-123","charges":[{"status":"PAID"}]}';
    $signature = '';

    expect(openssl_sign($payload, $signature, $keys['private'], OPENSSL_ALGO_SHA256))->toBeTrue();

    expect(pagSeguroGatewayForTest()->verifyWebhookSignature(
        ['webhook_public_key' => $keys['public']],
        $payload,
        base64_encode($signature),
    ))->toBeTrue()
        ->and(pagSeguroGatewayForTest()->verifyWebhookSignature(
            ['webhook_public_key' => $keys['public']],
            $payload.'tampered',
            base64_encode($signature),
        ))->toBeFalse();
});
