<?php

use App\Modules\Financial\Gateways\Contracts\PaymentGatewayInterface;
use App\Modules\Financial\Gateways\Data\ChargeIntent;
use App\Modules\Financial\Gateways\Data\ChargeResult;
use App\Modules\Financial\Gateways\PaymentGatewayManager;

uses(Tests\TestCase::class);

/**
 * Adaptador fake stateless e agnóstico de ledger para exercitar o contrato.
 */
class FakeGateway implements PaymentGatewayInterface
{
    public function __construct(
        private readonly string $id = 'stripe',
        private readonly string $label = 'Stripe',
    ) {}

    public function identifier(): string
    {
        return $this->id;
    }

    public function label(): string
    {
        return $this->label;
    }

    public function charge(array $credentials, ChargeIntent $intent): ChargeResult
    {
        return new ChargeResult(
            successful: true,
            status: 'pending',
            externalId: 'ch_fake',
            redirectUrl: 'https://pay.test/'.$intent->reference,
            raw: ['secret_seen' => $credentials['secret_key'] ?? null, 'amount' => $intent->amountCents],
        );
    }

    public function validateConfiguration(array $config): bool
    {
        return isset($config['secret_key']);
    }
}

it('registers adapters and looks them up by identifier', function (): void {
    $manager = new PaymentGatewayManager;
    $stripe = new FakeGateway('stripe', 'Stripe');
    $mp = new FakeGateway('mercadopago', 'Mercado Pago');

    $manager->register($stripe);
    $manager->register($mp);

    expect($manager->get('stripe'))->toBe($stripe)
        ->and($manager->get('mercadopago'))->toBe($mp)
        ->and($manager->get('pagseguro'))->toBeNull()
        ->and($manager->has('stripe'))->toBeTrue()
        ->and($manager->has('pagseguro'))->toBeFalse()
        ->and(array_keys($manager->all()))->toBe(['stripe', 'mercadopago']);
});

it('is bound as a singleton in the container', function (): void {
    expect(app(PaymentGatewayManager::class))->toBe(app(PaymentGatewayManager::class));
});

it('runs a charge through a registered adapter with neutral credentials and intent', function (): void {
    $manager = new PaymentGatewayManager;
    $manager->register(new FakeGateway('stripe'));

    $intent = new ChargeIntent(
        amountCents: 4990,
        currency: 'brl',
        reference: 'ORD-9001',
        description: 'Curso X',
        metadata: ['order_id' => 42],
    );

    $result = $manager->get('stripe')->charge(['secret_key' => 'sk_test_charge'], $intent);

    expect($result)->toBeInstanceOf(ChargeResult::class)
        ->and($result->successful)->toBeTrue()
        ->and($result->status)->toBe('pending')
        ->and($result->redirectUrl)->toBe('https://pay.test/ORD-9001')
        ->and($result->raw['secret_seen'])->toBe('sk_test_charge')
        ->and($result->raw['amount'])->toBe(4990);
});
