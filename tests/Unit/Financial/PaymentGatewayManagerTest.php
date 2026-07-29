<?php

use App\Modules\Financial\Contracts\GatewayConfigurationDefinition;
use App\Modules\Financial\Contracts\GatewayConfigurationRegistry;
use App\Modules\Financial\Enums\PaymentChargeStatus;
use App\Modules\Financial\Enums\PaymentConfirmationMode;
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
        private readonly bool $configurationValid = true,
    ) {}

    public function identifier(): string
    {
        return $this->id;
    }

    public function label(): string
    {
        return $this->label;
    }

    public function confirmationMode(): PaymentConfirmationMode
    {
        return PaymentConfirmationMode::Automatic;
    }

    public function configurationSchema(): GatewayConfigurationDefinition
    {
        return new GatewayConfigurationDefinition(
            identifier: $this->id,
            label: $this->label,
            fields: [
                'secret_key' => [
                    'label' => 'Chave secreta',
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
        return new ChargeResult(
            status: PaymentChargeStatus::Pending,
            externalId: 'ch_fake',
            redirectUrl: 'https://pay.test/'.$intent->reference,
            raw: ['secret_seen' => $credentials['secret_key'] ?? null, 'amount' => $intent->amountCents],
        );
    }

    public function validateConfiguration(array $config): bool
    {
        return $this->configurationValid && isset($config['secret_key']);
    }
}

it('registers adapters and looks them up by identifier', function (): void {
    $manager = app(PaymentGatewayManager::class);
    $stripe = new FakeGateway('stripe', 'Stripe');
    $mp = new FakeGateway('mercadopago', 'Mercado Pago');

    $manager->register($stripe);
    $manager->register($mp);

    expect($manager->get('stripe'))->toBe($stripe)
        ->and($manager->get('mercadopago'))->toBe($mp)
        ->and($manager->get('pagseguro'))->toBeNull()
        ->and($manager->has('stripe'))->toBeTrue()
        ->and($manager->has('pagseguro'))->toBeFalse()
        ->and(array_keys($manager->all()))->toBe(['cash', 'stripe', 'mercadopago']);
});

it('is bound as a singleton in the container', function (): void {
    expect(app(PaymentGatewayManager::class))->toBe(app(PaymentGatewayManager::class))
        ->and(app(GatewayConfigurationRegistry::class))->toBe(app(PaymentGatewayManager::class));
});

it('runs a charge through a registered adapter with neutral credentials and intent', function (): void {
    $manager = app(PaymentGatewayManager::class);
    $manager->register(new FakeGateway('stripe'));

    $intent = new ChargeIntent(
        amountCents: 4990,
        currency: 'brl',
        reference: 'ORD-9001',
        idempotencyKey: '3b4e1dc1-0ef6-46d8-9bea-aa992d719744',
        description: 'Curso X',
        metadata: ['order_id' => 42],
    );

    $result = $manager->get('stripe')->charge(['secret_key' => 'sk_test_charge'], $intent);

    expect($result)->toBeInstanceOf(ChargeResult::class)
        ->and($result->status)->toBe(PaymentChargeStatus::Pending)
        ->and($result->redirectUrl)->toBe('https://pay.test/ORD-9001')
        ->and($result->raw['secret_seen'])->toBe('sk_test_charge')
        ->and($result->raw['amount'])->toBe(4990);
});

it('returns a registered gateway configuration definition', function (): void {
    $manager = app(PaymentGatewayManager::class);
    $manager->register(new FakeGateway('stripe', 'Stripe'));

    expect($manager->definition('stripe'))->toBeInstanceOf(GatewayConfigurationDefinition::class)
        ->and($manager->definition('stripe')?->fields)->toHaveKey('secret_key')
        ->and($manager->definition('unavailable'))->toBeNull();
});

it('rejects unavailable gateways and unknown configuration fields', function (): void {
    $manager = app(PaymentGatewayManager::class);
    $manager->register(new FakeGateway('stripe'));

    $unavailable = $manager->validate('unavailable', ['secret_key' => 'sk_test_123']);
    $unknownField = $manager->validate('stripe', ['secret_key' => 'sk_test_123', 'public_key' => 'pk_test']);

    expect($unavailable->valid)->toBeFalse()
        ->and($unavailable->errors)->toHaveKey('gateway')
        ->and($unknownField->valid)->toBeFalse()
        ->and($unknownField->errors)->toHaveKey('public_key');
});

it('validates required secret schema fields without exposing their values', function (): void {
    $manager = app(PaymentGatewayManager::class);
    $manager->register(new FakeGateway('stripe'));

    $missing = $manager->validate('stripe', []);
    $valid = $manager->validate('stripe', ['secret_key' => 'sk_test_123']);
    $tooShort = $manager->validate('stripe', ['secret_key' => 'short']);
    $nonString = $manager->validate('stripe', ['secret_key' => ['must_not_leak']]);

    expect($missing->valid)->toBeFalse()
        ->and($missing->errors)->toHaveKey('secret_key')
        ->and(implode(' ', $missing->errors['secret_key']))->not->toContain('sk_test_123')
        ->and($valid->valid)->toBeTrue()
        ->and($valid->errors)->toBe([])
        ->and($tooShort->valid)->toBeFalse()
        ->and($tooShort->errors)->toHaveKey('secret_key')
        ->and(implode(' ', $tooShort->errors['secret_key']))->not->toContain('short')
        ->and($nonString->valid)->toBeFalse()
        ->and($nonString->errors)->toHaveKey('secret_key')
        ->and(implode(' ', $nonString->errors['secret_key']))->not->toContain('must_not_leak');
});

it('retains adapter configuration validation as defense in depth', function (): void {
    $manager = app(PaymentGatewayManager::class);
    $manager->register(new FakeGateway(configurationValid: false));

    $result = $manager->validate('stripe', ['secret_key' => 'sk_test_123']);

    expect($result->valid)->toBeFalse()
        ->and($result->errors)->toHaveKey('configuration')
        ->and(implode(' ', $result->errors['configuration']))->not->toContain('sk_test_123');
});
