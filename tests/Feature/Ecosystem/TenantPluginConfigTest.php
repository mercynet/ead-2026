<?php

use App\Modules\Ecosystem\Models\Plugin;
use App\Modules\Ecosystem\Models\TenantPluginConfig;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

it('encrypts the config at rest and hides it from serialization', function (): void {
    $tenant = makeTenant();
    $plugin = Plugin::factory()->gateway('stripe')->create();

    $config = TenantPluginConfig::factory()->create([
        'tenant_id' => $tenant->id,
        'plugin_id' => $plugin->id,
        'config' => ['secret_key' => 'sk_live_supersecret'],
    ]);

    $raw = DB::table('tenant_plugin_configs')->where('id', $config->id)->value('config');

    expect($raw)->toBeString()
        ->and($raw)->not->toContain('sk_live_supersecret')
        ->and($raw)->not->toContain('secret_key');

    expect($config->toArray())->not->toHaveKey('config');

    $fresh = $config->fresh();

    expect($fresh->credentials())->toBe(['secret_key' => 'sk_live_supersecret'])
        ->and($fresh->get('secret_key'))->toBe('sk_live_supersecret');
});

it('keeps one config row per tenant+plugin', function (): void {
    $tenant = makeTenant();
    $plugin = Plugin::factory()->create();

    TenantPluginConfig::factory()->create(['tenant_id' => $tenant->id, 'plugin_id' => $plugin->id]);

    expect(fn () => TenantPluginConfig::factory()->create([
        'tenant_id' => $tenant->id,
        'plugin_id' => $plugin->id,
    ]))->toThrow(QueryException::class);
});

it('scopes enabled configs', function (): void {
    $tenant = makeTenant();

    TenantPluginConfig::factory()->create(['tenant_id' => $tenant->id]);
    TenantPluginConfig::factory()->disabled()->create(['tenant_id' => $tenant->id]);

    expect(TenantPluginConfig::query()->enabled()->count())->toBe(1);
});
