<?php

use App\Modules\Ecosystem\Models\Plugin;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Schema;

it('loads the normalized plugin catalog schema', function (): void {
    expect(Schema::hasTable('plugins'))->toBeTrue();
    expect(Schema::hasColumns('plugins', [
        'slug',
        'name',
        'capability_key',
        'kind',
        'status',
        'visibility',
        'tier',
        'is_curated',
        'directory_name',
        'short_description',
        'long_description',
        'logo_path',
        'default_locale',
        'support_url',
        'docs_url',
    ]))->toBeTrue();
});

it('scopes published and tenant-visible plugins', function (): void {
    Plugin::factory()->published()->create();          // público + publicado
    Plugin::factory()->create();                        // draft
    Plugin::factory()->published()->internal()->create(); // publicado mas interno

    expect(Plugin::query()->published()->count())->toBe(2)
        ->and(Plugin::query()->visibleToTenants()->count())->toBe(1);
});

it('models a gateway plugin bound to an adapter slug', function (): void {
    $stripe = Plugin::factory()->published()->gateway('stripe')->create();

    expect($stripe->isGateway())->toBeTrue()
        ->and($stripe->isLive())->toBeTrue()
        ->and($stripe->capability_key)->toBe('gateway.stripe')
        ->and($stripe->gatewaySlug())->toBe('stripe');

    $feature = Plugin::factory()->create(['kind' => 'feature']);
    expect($feature->isGateway())->toBeFalse()
        ->and($feature->gatewaySlug())->toBeNull();
});

it('enforces unique slug and capability_key across the catalog', function (): void {
    Plugin::factory()->create(['slug' => 'forum', 'capability_key' => 'feature.forum']);

    expect(fn () => Plugin::factory()->create(['slug' => 'forum', 'capability_key' => 'feature.other']))
        ->toThrow(QueryException::class);

    expect(fn () => Plugin::factory()->create(['slug' => 'other', 'capability_key' => 'feature.forum']))
        ->toThrow(QueryException::class);
});
