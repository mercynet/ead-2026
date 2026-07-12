<?php

use App\Modules\Financial\Exceptions\GatewayResolutionException;
use App\Modules\Financial\Gateways\Contracts\PaymentGatewayInterface;
use App\Modules\Financial\Gateways\Data\ChargeIntent;
use App\Modules\Financial\Gateways\Data\ChargeResult;
use App\Modules\Financial\Gateways\PaymentGatewayManager;
use App\Modules\Financial\Gateways\PlatformGatewayResolver;
use App\Modules\Financial\Models\PlatformPaymentGateway;
use Illuminate\Support\Facades\DB;

class PlatformFakeGateway implements PaymentGatewayInterface
{
    public function __construct(private readonly string $id = 'stripe') {}

    public function identifier(): string
    {
        return $this->id;
    }

    public function label(): string
    {
        return 'Stripe';
    }

    public function charge(array $credentials, ChargeIntent $intent): ChargeResult
    {
        return new ChargeResult(
            successful: true,
            status: 'pending',
            externalId: 'ch_'.$intent->reference,
            raw: ['secret_seen' => $credentials['secret_key'] ?? null],
        );
    }

    public function validateConfiguration(array $config): bool
    {
        return isset($config['secret_key']);
    }
}

function registerPlatformStripe(): void
{
    app(PaymentGatewayManager::class)->register(new PlatformFakeGateway('stripe'));
}

function platformResolver(): PlatformGatewayResolver
{
    return app(PlatformGatewayResolver::class);
}

it('encrypts the platform gateway configuration and hides it from serialization', function (): void {
    $gateway = PlatformPaymentGateway::factory()->create([
        'configuration' => ['mode' => 'sandbox', 'secret_key' => 'sk_live_platform'],
    ]);

    $raw = DB::table('platform_payment_gateways')->where('id', $gateway->id)->value('configuration');

    expect($raw)->toBeString()
        ->and($raw)->not->toContain('sk_live_platform')
        ->and($gateway->toArray())->not->toHaveKey('configuration')
        ->and($gateway->fresh()->credentials())->toBe(['mode' => 'sandbox', 'secret_key' => 'sk_live_platform']);
});

it('resolves the active platform gateway and charges', function (): void {
    registerPlatformStripe();
    PlatformPaymentGateway::factory()->create([
        'configuration' => ['secret_key' => 'sk_test_platform'],
    ]);

    $resolved = platformResolver()->resolve();

    $result = $resolved->charge(new ChargeIntent(amountCents: 2990, currency: 'brl', reference: 'PLAT-1'));

    expect($result->successful)->toBeTrue()
        ->and($result->raw['secret_seen'])->toBe('sk_test_platform');
});

it('throws when no active platform gateway exists', function (): void {
    registerPlatformStripe();
    PlatformPaymentGateway::factory()->inactive()->create();

    expect(platformResolver()->hasActive())->toBeFalse();
    expect(fn () => platformResolver()->resolve())
        ->toThrow(GatewayResolutionException::class);
});

it('throws when the active platform gateway has no registered adapter', function (): void {
    PlatformPaymentGateway::factory()->gateway('pagseguro')->create();

    expect(fn () => platformResolver()->resolve())
        ->toThrow(GatewayResolutionException::class, 'sem adaptador registrado');
});

it('makeDefault demotes the other platform gateways atomically', function (): void {
    $stripe = PlatformPaymentGateway::factory()->create(['gateway_slug' => 'stripe', 'is_default' => true]);
    $mp = PlatformPaymentGateway::factory()->gateway('mercadopago')->create(['is_default' => false]);

    $mp->makeDefault();

    expect($mp->fresh()->is_default)->toBeTrue()
        ->and($stripe->fresh()->is_default)->toBeFalse()
        ->and(PlatformPaymentGateway::query()->where('is_default', true)->count())->toBe(1);
});
