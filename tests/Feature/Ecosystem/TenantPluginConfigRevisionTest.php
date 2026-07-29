<?php

use App\Modules\Ecosystem\Models\Plugin;
use App\Modules\Ecosystem\Models\PluginActivation;
use App\Modules\Ecosystem\Models\TenantPluginConfig;
use App\Modules\Ecosystem\Models\TenantPluginConfigRevision;
use App\Modules\Ecosystem\Services\EcosystemTenantGatewayProvider;
use Illuminate\Support\Facades\DB;

it('creates encrypted redacted revisions for direct configuration persistence', function (): void {
    $tenant = makeTenant();
    $plugin = Plugin::factory()->gateway('stripe')->create();

    $config = TenantPluginConfig::factory()->create([
        'tenant_id' => $tenant->id,
        'plugin_id' => $plugin->id,
        'config' => ['secret_key' => 'sk_revision_initial'],
    ]);

    $revision = TenantPluginConfigRevision::query()->firstOrFail();
    $raw = DB::table('tenant_plugin_config_revisions')->where('id', $revision->id)->value('config');

    expect($revision->tenant_plugin_config_id)->toBe($config->id)
        ->and($revision->configuration_version)->toBe($config->configuration_version)
        ->and($revision->credentials())->toBe(['secret_key' => 'sk_revision_initial'])
        ->and($revision->toArray())->not->toHaveKey('config')
        ->and($raw)->toBeString()
        ->not->toContain('sk_revision_initial')
        ->not->toContain('secret_key');
});

it('preserves historical gateway credentials after rotation and disablement', function (): void {
    $tenant = makeTenant();
    $plugin = Plugin::factory()->published()->gateway('stripe')->create();
    PluginActivation::factory()->create(['tenant_id' => $tenant->id, 'plugin_id' => $plugin->id]);
    $config = TenantPluginConfig::factory()->create([
        'tenant_id' => $tenant->id,
        'plugin_id' => $plugin->id,
        'config' => ['secret_key' => 'sk_revision_prior'],
    ]);
    $priorVersion = $config->configuration_version;

    $config->update(['config' => ['secret_key' => 'sk_revision_current']]);
    $config->update(['enabled' => false]);
    $plugin->update(['status' => 'draft']);
    PluginActivation::query()->where('tenant_id', $tenant->id)->where('plugin_id', $plugin->id)->update(['status' => 'inactive']);

    $provider = app(EcosystemTenantGatewayProvider::class);
    $historical = $provider->activeForIdentity($tenant, $config->id, $priorVersion);

    expect($historical)->not->toBeNull()
        ->and($historical->slug)->toBe('stripe')
        ->and($historical->credentials)->toBe(['secret_key' => 'sk_revision_prior'])
        ->and($historical->tenantPluginConfigId)->toBe($config->id)
        ->and($historical->configurationVersion)->toBe($priorVersion)
        ->and(TenantPluginConfigRevision::query()->where('tenant_plugin_config_id', $config->id)->count())->toBe(3)
        ->and($provider->activeFor($tenant))->toBeNull();
});

it('denies cross-tenant historical configuration identities', function (): void {
    $tenantA = makeTenant();
    $tenantB = makeTenant();
    $plugin = Plugin::factory()->gateway('stripe')->create();
    $config = TenantPluginConfig::factory()->create([
        'tenant_id' => $tenantB->id,
        'plugin_id' => $plugin->id,
    ]);

    expect(app(EcosystemTenantGatewayProvider::class)->activeForIdentity(
        $tenantA,
        $config->id,
        $config->configuration_version,
    ))->toBeNull();

});
