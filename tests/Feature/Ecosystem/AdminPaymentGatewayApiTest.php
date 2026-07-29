<?php

use App\Modules\Core\Enums\UserType;
use App\Modules\Ecosystem\Models\Plugin;
use App\Modules\Ecosystem\Models\PluginActivation;
use App\Modules\Ecosystem\Models\TenantPluginConfig;
use App\Modules\Financial\Contracts\GatewayConfigurationDefinition;
use App\Modules\Financial\Enums\PaymentConfirmationMode;
use App\Modules\Financial\Gateways\Contracts\PaymentGatewayInterface;
use App\Modules\Financial\Gateways\Data\ChargeIntent;
use App\Modules\Financial\Gateways\Data\ChargeResult;
use App\Modules\Financial\Gateways\PaymentGatewayManager;
use Illuminate\Support\Facades\DB;

class EcosystemGatewayApiFake implements PaymentGatewayInterface
{
    public function identifier(): string
    {
        return 'fake-gateway';
    }

    public function label(): string
    {
        return 'Fake gateway';
    }

    public function confirmationMode(): PaymentConfirmationMode
    {
        return PaymentConfirmationMode::Automatic;
    }

    public function configurationSchema(): GatewayConfigurationDefinition
    {
        return new GatewayConfigurationDefinition('fake-gateway', 'Fake gateway', [
            'public_key' => ['label' => 'Public key', 'input' => 'text', 'required' => true, 'secret' => false, 'rules' => ['string']],
            'secret_key' => ['label' => 'Secret key', 'input' => 'password', 'required' => true, 'secret' => true, 'rules' => ['string']],
        ]);
    }

    public function charge(array $credentials, ChargeIntent $intent): ChargeResult
    {
        throw new LogicException('Not used by gateway admin tests.');
    }

    public function validateConfiguration(array $config): bool
    {
        return ($config['public_key'] ?? null) !== 'adapter-rejected';
    }
}

beforeEach(function (): void {
    app(PaymentGatewayManager::class)->register(new EcosystemGatewayApiFake);
});

function activeGatewayPlugin(string $slug = 'fake-gateway'): Plugin
{
    return Plugin::factory()->gateway($slug)->create([
        'capability_key' => 'gateway.'.$slug,
        'status' => 'published',
    ]);
}

function activateGateway(\App\Modules\Core\Models\Tenant $tenant, Plugin $plugin): void
{
    PluginActivation::factory()->create(['tenant_id' => $tenant->id, 'plugin_id' => $plugin->id]);
}

it('requires authentication and gateway permission', function (): void {
    $tenant = makeTenant();

    assertApiErrorEnvelope($this->getJson('/api/v1/admin/payment-gateways', tenantHeaders($tenant)), 401, 'unauthenticated');

    [, $headers] = actingAsUserType(UserType::Instructor, $tenant);

    $this->getJson('/api/v1/admin/payment-gateways', $headers)->assertForbidden();
});

it('requires authentication and gateway permission to update', function (): void {
    $tenant = makeTenant();
    $plugin = activeGatewayPlugin();
    activateGateway($tenant, $plugin);

    assertApiErrorEnvelope(
        $this->putJson('/api/v1/admin/payment-gateways/fake-gateway', ['enabled' => false], tenantHeaders($tenant)),
        401,
        'unauthenticated',
    );

    [, $headers] = actingAsUserType(UserType::Instructor, $tenant);

    assertApiErrorEnvelope(
        $this->putJson('/api/v1/admin/payment-gateways/fake-gateway', ['enabled' => false], $headers),
        403,
        'access_denied',
    );
});

it('lists only active live gateway entitlements in stable cursor order without secrets', function (): void {
    $tenant = makeTenant();
    [, $headers] = actingAsUserType(UserType::Admin, $tenant);
    $later = activeGatewayPlugin('z-fake-gateway');
    $first = activeGatewayPlugin('a-fake-gateway');
    activateGateway($tenant, $later);
    activateGateway($tenant, $first);
    TenantPluginConfig::factory()->create([
        'tenant_id' => $tenant->id,
        'plugin_id' => $first->id,
        'config' => ['public_key' => 'pk_test', 'secret_key' => 'sk_super_secret'],
    ]);
    $otherTenant = makeTenant();
    $other = activeGatewayPlugin('other-gateway');
    activateGateway($otherTenant, $other);
    $inactive = activeGatewayPlugin('inactive-gateway');
    PluginActivation::factory()->inactive()->create(['tenant_id' => $tenant->id, 'plugin_id' => $inactive->id]);

    $response = $this->getJson('/api/v1/admin/payment-gateways', $headers)
        ->assertSuccessful()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.plugin', 'z-fake-gateway')
        ->assertJsonPath('data.1.plugin', 'a-fake-gateway')
        ->assertJsonMissingPath('data.1.configuration.secret_key')
        ->assertJsonMissing(['sk_super_secret', 'secret_key'])
        ->assertJsonStructure(['data', 'links', 'meta']);

    expect($response->json('data.1.available'))->toBeFalse();
});

it('marks entitlement unavailable when no adapter schema exists', function (): void {
    $tenant = makeTenant();
    [, $headers] = actingAsUserType(UserType::Admin, $tenant);
    $plugin = activeGatewayPlugin('missing-adapter');
    activateGateway($tenant, $plugin);

    $this->getJson('/api/v1/admin/payment-gateways', $headers)
        ->assertSuccessful()
        ->assertJsonPath('data.0.available', false)
        ->assertJsonPath('data.0.configuration', [])
        ->assertJsonPath('data.0.configuration_schema', []);
});

it('preserves unavailable gateway credentials when disabling without configuration', function (): void {
    $tenant = makeTenant();
    [, $headers] = actingAsUserType(UserType::Admin, $tenant);
    $plugin = activeGatewayPlugin('missing-adapter');
    activateGateway($tenant, $plugin);
    $config = TenantPluginConfig::factory()->create([
        'tenant_id' => $tenant->id,
        'plugin_id' => $plugin->id,
        'enabled' => true,
        'config' => ['legacy_key' => 'legacy-value', 'secret_key' => 'secret-value'],
    ]);

    $this->putJson('/api/v1/admin/payment-gateways/missing-adapter', ['enabled' => false], $headers)
        ->assertSuccessful()
        ->assertJsonPath('data.available', false)
        ->assertJsonPath('data.configuration', [])
        ->assertJsonMissing(['legacy-value', 'secret-value', 'legacy_key', 'secret_key']);

    expect($config->fresh()->credentials())->toBe(['legacy_key' => 'legacy-value', 'secret_key' => 'secret-value'])
        ->and($config->fresh()->enabled)->toBeFalse();

    $this->putJson('/api/v1/admin/payment-gateways/missing-adapter', [
        'enabled' => false,
        'configuration' => ['legacy_key' => 'change-me'],
    ], $headers)->assertUnprocessable();

    expect($config->fresh()->credentials())->toBe(['legacy_key' => 'legacy-value', 'secret_key' => 'secret-value'])
        ->and($config->fresh()->enabled)->toBeFalse();
});

it('returns 404 for missing inactive and cross-tenant gateway updates', function (): void {
    $tenant = makeTenant();
    [, $headers] = actingAsUserType(UserType::Admin, $tenant);
    $plugin = activeGatewayPlugin();
    $otherTenant = makeTenant();
    activateGateway($otherTenant, $plugin);

    foreach (['missing-gateway', 'fake-gateway'] as $slug) {
        assertApiErrorEnvelope($this->putJson('/api/v1/admin/payment-gateways/'.$slug, ['enabled' => false], $headers), 404, 'not_found');
    }
});

it('returns 404 for inactive, non-live and non-gateway plugins', function (): void {
    $tenant = makeTenant();
    [, $headers] = actingAsUserType(UserType::Admin, $tenant);
    $inactive = activeGatewayPlugin('inactive-gateway');
    PluginActivation::factory()->inactive()->create(['tenant_id' => $tenant->id, 'plugin_id' => $inactive->id]);
    $nonLive = Plugin::factory()->gateway('draft-gateway')->create([
        'capability_key' => 'gateway.draft-gateway',
        'status' => 'draft',
    ]);
    activateGateway($tenant, $nonLive);
    $nonGateway = Plugin::factory()->create(['slug' => 'not-a-gateway', 'status' => 'published']);
    activateGateway($tenant, $nonGateway);

    foreach (['inactive-gateway', 'draft-gateway', 'not-a-gateway'] as $slug) {
        assertApiErrorEnvelope(
            $this->putJson('/api/v1/admin/payment-gateways/'.$slug, ['enabled' => false], $headers),
            404,
            'not_found',
        );
    }
});

it('rejects invalid and unknown configuration without changing stored credentials', function (): void {
    $tenant = makeTenant();
    [, $headers] = actingAsUserType(UserType::Admin, $tenant);
    $plugin = activeGatewayPlugin();
    activateGateway($tenant, $plugin);
    $config = TenantPluginConfig::factory()->create([
        'tenant_id' => $tenant->id,
        'plugin_id' => $plugin->id,
        'config' => ['public_key' => 'old-public', 'secret_key' => 'old-secret'],
        'enabled' => false,
    ]);

    $this->putJson('/api/v1/admin/payment-gateways/fake-gateway', ['enabled' => true, 'configuration' => ['unknown' => 'value']], $headers)
        ->assertUnprocessable();

    expect($config->fresh()->credentials())->toBe(['public_key' => 'old-public', 'secret_key' => 'old-secret'])
        ->and($config->fresh()->enabled)->toBeFalse();
});

it('reports configured false when persisted configuration fails adapter validation', function (): void {
    $tenant = makeTenant();
    [, $headers] = actingAsUserType(UserType::Admin, $tenant);
    $plugin = activeGatewayPlugin();
    activateGateway($tenant, $plugin);
    TenantPluginConfig::factory()->create([
        'tenant_id' => $tenant->id,
        'plugin_id' => $plugin->id,
        'config' => ['public_key' => 'adapter-rejected', 'secret_key' => 'sk_complete'],
    ]);

    $this->getJson('/api/v1/admin/payment-gateways', $headers)
        ->assertSuccessful()
        ->assertJsonPath('data.0.available', true)
        ->assertJsonPath('data.0.configured', false);
});

it('removes legacy configuration keys during valid updates', function (): void {
    $tenant = makeTenant();
    [, $headers] = actingAsUserType(UserType::Admin, $tenant);
    $plugin = activeGatewayPlugin();
    activateGateway($tenant, $plugin);
    $config = TenantPluginConfig::factory()->create([
        'tenant_id' => $tenant->id,
        'plugin_id' => $plugin->id,
        'config' => ['public_key' => 'pk_old', 'legacy_key' => 'remove-me'],
    ]);

    $this->putJson('/api/v1/admin/payment-gateways/fake-gateway', [
        'enabled' => true,
        'configuration' => ['secret_key' => 'sk_live'],
    ], $headers)->assertSuccessful()->assertJsonMissing(['remove-me', 'sk_live']);

    expect($config->fresh()->credentials())->toBe(['public_key' => 'pk_old', 'secret_key' => 'sk_live']);
});

it('persists encrypted config, preserves omitted secrets and disables prior gateway', function (): void {
    $tenant = makeTenant();
    [, $headers] = actingAsUserType(UserType::Admin, $tenant);
    $plugin = activeGatewayPlugin();
    $prior = activeGatewayPlugin('prior-gateway');
    activateGateway($tenant, $plugin);
    activateGateway($tenant, $prior);
    $priorConfig = TenantPluginConfig::factory()->create(['tenant_id' => $tenant->id, 'plugin_id' => $prior->id, 'enabled' => true]);

    $this->putJson('/api/v1/admin/payment-gateways/fake-gateway', [
        'enabled' => true,
        'configuration' => ['public_key' => 'pk_live', 'secret_key' => 'sk_live'],
    ], $headers)->assertSuccessful()
        ->assertJsonMissing(['sk_live'])
        ->assertJsonMissingPath('data.configuration.secret_key');

    $config = TenantPluginConfig::query()->where('tenant_id', $tenant->id)->where('plugin_id', $plugin->id)->firstOrFail();
    expect(DB::table('tenant_plugin_configs')->where('id', $config->id)->value('config'))->not->toContain('sk_live')
        ->and($priorConfig->fresh()->enabled)->toBeFalse();

    $this->putJson('/api/v1/admin/payment-gateways/fake-gateway', ['enabled' => false], $headers)
        ->assertSuccessful()
        ->assertJsonMissing(['sk_live'])
        ->assertJsonMissingPath('data.configuration.secret_key');

    expect($config->fresh()->credentials())->toBe(['public_key' => 'pk_live', 'secret_key' => 'sk_live'])
        ->and($config->fresh()->enabled)->toBeFalse();

    $this->putJson('/api/v1/admin/payment-gateways/fake-gateway', [
        'enabled' => false,
        'configuration' => ['public_key' => 'pk_changed'],
    ], $headers)->assertSuccessful()
        ->assertJsonMissing(['sk_live'])
        ->assertJsonMissingPath('data.configuration.secret_key');

    expect($config->fresh()->credentials())->toBe(['public_key' => 'pk_changed', 'secret_key' => 'sk_live'])
        ->and($config->fresh()->enabled)->toBeFalse();
});

it('rotates configuration version when gateway configuration or enabled state changes', function (): void {
    $tenant = makeTenant();
    [, $headers] = actingAsUserType(UserType::Admin, $tenant);
    $plugin = activeGatewayPlugin();
    activateGateway($tenant, $plugin);
    $config = TenantPluginConfig::factory()->create([
        'tenant_id' => $tenant->id,
        'plugin_id' => $plugin->id,
        'enabled' => false,
        'config' => ['public_key' => 'pk_old', 'secret_key' => 'sk_old'],
    ]);

    $originalVersion = $config->configuration_version;

    $this->putJson('/api/v1/admin/payment-gateways/fake-gateway', [
        'enabled' => true,
        'configuration' => ['public_key' => 'pk_new'],
    ], $headers)->assertSuccessful()->assertJsonMissing(['sk_old']);

    $configuredVersion = $config->fresh()->configuration_version;
    expect($configuredVersion)->not->toBe($originalVersion);

    $this->putJson('/api/v1/admin/payment-gateways/fake-gateway', ['enabled' => false], $headers)
        ->assertSuccessful();

    expect($config->fresh()->configuration_version)
        ->not->toBe($configuredVersion)
        ->and($config->fresh()->enabled)->toBeFalse();
});

it('prohibits ownership fields and unavailable gateway enablement', function (): void {
    $tenant = makeTenant();
    [, $headers] = actingAsUserType(UserType::Admin, $tenant);
    $plugin = activeGatewayPlugin();
    activateGateway($tenant, $plugin);

    $this->putJson('/api/v1/admin/payment-gateways/fake-gateway', ['enabled' => false, 'tenant_id' => 999], $headers)
        ->assertUnprocessable();
    $unavailable = activeGatewayPlugin('not-registered');
    activateGateway($tenant, $unavailable);

    $this->putJson('/api/v1/admin/payment-gateways/not-registered', ['enabled' => true], $headers)
        ->assertUnprocessable();
});
