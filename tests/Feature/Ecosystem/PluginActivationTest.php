<?php

use App\Modules\Ecosystem\Models\Plugin;
use App\Modules\Ecosystem\Models\PluginActivation;
use Illuminate\Database\QueryException;

it('activates a plugin for a tenant, one row per tenant+plugin', function (): void {
    $tenant = makeTenant();
    $plugin = Plugin::factory()->create();

    $activation = PluginActivation::factory()->create([
        'tenant_id' => $tenant->id,
        'plugin_id' => $plugin->id,
    ]);

    expect($activation->isActive())->toBeTrue()
        ->and($activation->plugin->is($plugin))->toBeTrue()
        ->and(PluginActivation::query()->active()->count())->toBe(1);

    expect(fn () => PluginActivation::factory()->create([
        'tenant_id' => $tenant->id,
        'plugin_id' => $plugin->id,
    ]))->toThrow(QueryException::class);
});

it('does not count inactive activations as active', function (): void {
    $tenant = makeTenant();

    PluginActivation::factory()->inactive()->create(['tenant_id' => $tenant->id]);

    expect(PluginActivation::query()->active()->count())->toBe(0);
});

it('isolates activations per tenant', function (): void {
    $tenantA = makeTenant();
    $tenantB = makeTenant();
    $plugin = Plugin::factory()->create();

    PluginActivation::factory()->create(['tenant_id' => $tenantB->id, 'plugin_id' => $plugin->id]);

    expect(PluginActivation::query()->where('tenant_id', $tenantA->id)->active()->exists())->toBeFalse()
        ->and(PluginActivation::query()->where('tenant_id', $tenantB->id)->active()->exists())->toBeTrue();
});
