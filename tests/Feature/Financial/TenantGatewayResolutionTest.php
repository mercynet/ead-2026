<?php

use App\Modules\Ecosystem\Models\Plugin;
use App\Modules\Ecosystem\Models\PluginActivation;
use App\Modules\Ecosystem\Models\TenantPluginConfig;
use App\Modules\Financial\Exceptions\GatewayResolutionException;
use App\Modules\Financial\Gateways\Contracts\PaymentGatewayInterface;
use App\Modules\Financial\Gateways\Data\ChargeIntent;
use App\Modules\Financial\Gateways\Data\ChargeResult;
use App\Modules\Financial\Gateways\Data\ResolvedGateway;
use App\Modules\Financial\Gateways\PaymentGatewayManager;
use App\Modules\Financial\Gateways\TenantGatewayResolver;

/**
 * Adaptador fake para a resolução (nome distinto do usado no teste Unit).
 */
class ResolverFakeGateway implements PaymentGatewayInterface
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

function registerStripeAdapter(): void
{
    app(PaymentGatewayManager::class)->register(new ResolverFakeGateway('stripe'));
}

/**
 * @param  array<string, mixed>  $config
 */
function seedGatewayPlugin(
    App\Modules\Core\Models\Tenant $tenant,
    string $activationStatus = 'active',
    bool $configEnabled = true,
    array $config = ['secret_key' => 'sk_test_x'],
): Plugin {
    $plugin = Plugin::factory()->published()->gateway('stripe')->create();

    PluginActivation::factory()->create([
        'tenant_id' => $tenant->id,
        'plugin_id' => $plugin->id,
        'status' => $activationStatus,
    ]);

    TenantPluginConfig::factory()->create([
        'tenant_id' => $tenant->id,
        'plugin_id' => $plugin->id,
        'enabled' => $configEnabled,
        'config' => $config,
    ]);

    return $plugin;
}

function resolver(): TenantGatewayResolver
{
    return app(TenantGatewayResolver::class);
}

it('resolves adapter and credentials as an atomic unit and charges', function (): void {
    $tenant = makeTenant();
    registerStripeAdapter();
    seedGatewayPlugin($tenant, config: ['secret_key' => 'sk_test_charge']);

    $resolved = resolver()->resolve($tenant);

    expect($resolved)->toBeInstanceOf(ResolvedGateway::class);

    $result = $resolved->charge(new ChargeIntent(
        amountCents: 4990,
        currency: 'brl',
        reference: 'ORD-1',
    ));

    expect($result->successful)->toBeTrue()
        ->and($result->raw['secret_seen'])->toBe('sk_test_charge');
});

it('throws when the tenant has no active gateway', function (): void {
    $tenant = makeTenant();
    registerStripeAdapter();

    expect(fn () => resolver()->resolve($tenant))
        ->toThrow(GatewayResolutionException::class);

    expect(resolver()->hasActiveFor($tenant))->toBeFalse();
});

it('does not resolve when the plugin activation is inactive', function (): void {
    $tenant = makeTenant();
    registerStripeAdapter();
    seedGatewayPlugin($tenant, activationStatus: 'inactive');

    expect(fn () => resolver()->resolve($tenant))
        ->toThrow(GatewayResolutionException::class);
});

it('does not resolve when the tenant config is disabled', function (): void {
    $tenant = makeTenant();
    registerStripeAdapter();
    seedGatewayPlugin($tenant, configEnabled: false);

    expect(fn () => resolver()->resolve($tenant))
        ->toThrow(GatewayResolutionException::class);
});

it('throws when the active gateway has no registered adapter', function (): void {
    $tenant = makeTenant();
    // adaptador NÃO registrado
    seedGatewayPlugin($tenant);

    expect(fn () => resolver()->resolve($tenant))
        ->toThrow(GatewayResolutionException::class, 'sem adaptador registrado');
});

it('throws when the stored config is invalid for the adapter', function (): void {
    $tenant = makeTenant();
    registerStripeAdapter();
    seedGatewayPlugin($tenant, config: ['publishable_key' => 'pk_only']); // falta secret_key

    expect(fn () => resolver()->resolve($tenant))
        ->toThrow(GatewayResolutionException::class, 'inválida');
});

it('does not resolve a gateway configured for another tenant', function (): void {
    $tenantA = makeTenant();
    $tenantB = makeTenant();
    registerStripeAdapter();
    seedGatewayPlugin($tenantB);

    expect(resolver()->hasActiveFor($tenantB))->toBeTrue()
        ->and(resolver()->hasActiveFor($tenantA))->toBeFalse();

    expect(fn () => resolver()->resolve($tenantA))
        ->toThrow(GatewayResolutionException::class);
});
