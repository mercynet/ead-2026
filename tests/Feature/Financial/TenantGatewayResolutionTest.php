<?php

use App\Modules\Ecosystem\Models\Plugin;
use App\Modules\Ecosystem\Models\PluginActivation;
use App\Modules\Ecosystem\Models\TenantPluginConfig;
use App\Modules\Financial\Contracts\GatewayConfigurationDefinition;
use App\Modules\Financial\Enums\PaymentChargeStatus;
use App\Modules\Financial\Enums\PaymentConfirmationMode;
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

    public function confirmationMode(): PaymentConfirmationMode
    {
        return PaymentConfirmationMode::Automatic;
    }

    public function configurationSchema(): GatewayConfigurationDefinition
    {
        return new GatewayConfigurationDefinition(
            identifier: $this->id,
            label: $this->label(),
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
        idempotencyKey: '3b4e1dc1-0ef6-46d8-9bea-aa992d719744',
    ));

    expect($result->status)->toBe(PaymentChargeStatus::Pending)
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

it('resolves only the exact current gateway configuration identity', function (): void {
    $tenant = makeTenant();
    registerStripeAdapter();
    $plugin = seedGatewayPlugin($tenant, config: ['secret_key' => 'sk_exact_identity']);
    $config = TenantPluginConfig::query()->where('tenant_id', $tenant->id)->where('plugin_id', $plugin->id)->firstOrFail();

    $resolved = resolver()->resolveExact($tenant, $config->id, $config->configuration_version, 'stripe');

    expect($resolved->tenantPluginConfigId)->toBe($config->id)
        ->and($resolved->configurationVersion)->toBe($config->configuration_version);
});

it('rejects missing revisions and resolves disabled gateway configuration identities', function (): void {
    $tenant = makeTenant();
    registerStripeAdapter();
    $plugin = seedGatewayPlugin($tenant);
    $config = TenantPluginConfig::query()->where('tenant_id', $tenant->id)->where('plugin_id', $plugin->id)->firstOrFail();

    expect(fn () => resolver()->resolveExact($tenant, $config->id, 'stale-version', 'stripe'))
        ->toThrow(GatewayResolutionException::class);

    $config->update(['enabled' => false]);

    expect(resolver()->resolveExact($tenant, $config->id, $config->configuration_version, 'stripe')->configurationVersion)
        ->toBe($config->configuration_version);
});

it('resolves prior exact gateway configuration after direct rotation', function (): void {
    $tenant = makeTenant();
    registerStripeAdapter();
    $plugin = seedGatewayPlugin($tenant, config: ['secret_key' => 'sk_before_rotation']);
    $config = TenantPluginConfig::query()->where('tenant_id', $tenant->id)->where('plugin_id', $plugin->id)->firstOrFail();
    $priorVersion = $config->configuration_version;

    expect(resolver()->resolveExact($tenant, $config->id, $priorVersion, 'stripe')->configurationVersion)->toBe($priorVersion);

    $config->update(['config' => ['secret_key' => 'sk_after_rotation']]);
    $config->refresh();

    $resolved = resolver()->resolveExact($tenant, $config->id, $priorVersion, 'stripe');

    expect($config->configuration_version)->not->toBe($priorVersion)
        ->and($resolved->credentials)->toBe(['secret_key' => 'sk_before_rotation']);
});

it('retains migrated legacy configuration identities', function (): void {
    $tenant = makeTenant();
    registerStripeAdapter();
    $plugin = seedGatewayPlugin($tenant);
    $config = TenantPluginConfig::query()->where('tenant_id', $tenant->id)->where('plugin_id', $plugin->id)->firstOrFail();
    $legacyVersion = 'legacy-'.$config->id;
    $config->forceFill(['configuration_version' => $legacyVersion])->save();

    expect(resolver()->resolve($tenant)->configurationVersion)->toBe($legacyVersion)
        ->and(resolver()->resolveExact($tenant, $config->id, $legacyVersion, 'stripe')->configurationVersion)->toBe($legacyVersion);
});

it('rejects an exact configuration resolved with another gateway slug', function (): void {
    $tenant = makeTenant();
    registerStripeAdapter();
    $plugin = seedGatewayPlugin($tenant);
    $config = TenantPluginConfig::query()->where('tenant_id', $tenant->id)->where('plugin_id', $plugin->id)->firstOrFail();

    expect(fn () => resolver()->resolveExact($tenant, $config->id, $config->configuration_version, 'other'))
        ->toThrow(GatewayResolutionException::class);
});
