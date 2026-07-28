<?php

use App\Modules\Core\Enums\UserType;
use App\Modules\Core\Models\Tenant;
use App\Modules\Core\Models\User;
use App\Modules\Ecosystem\Contracts\TenantGatewayProvider;
use App\Modules\Ecosystem\Models\Plugin;
use App\Modules\Ecosystem\Models\PluginActivation;
use App\Modules\Ecosystem\Models\TenantPluginConfig;
use App\Modules\Financial\Gateways\Adapters\CashPaymentGateway;
use App\Modules\Financial\Gateways\TenantGatewayResolver;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

it('provisions a tenant and first admin with the admin role', function (): void {
    $this->artisan('tenant:provision', [
        '--name' => 'Escola Piloto',
        '--domain' => 'piloto.local',
        '--admin-name' => 'Admin Piloto',
        '--admin-email' => 'admin@piloto.local',
        '--admin-password' => 'senha-forte-123',
    ])->assertExitCode(0);

    $tenant = Tenant::query()->where('domain', 'piloto.local')->firstOrFail();
    expect($tenant->is_active)->toBeTrue()
        ->and($tenant->name)->toBe('Escola Piloto');

    $admin = User::query()->where('email', 'admin@piloto.local')->firstOrFail();
    expect($admin->tenant_id)->toBe($tenant->id)
        ->and($admin->user_type)->toBe(UserType::Admin)
        ->and($admin->hasRole('admin'))->toBeTrue()
        ->and(Hash::check('senha-forte-123', $admin->password))->toBeTrue();
});

it('is idempotent — running twice creates no duplicates', function (): void {
    $args = [
        '--name' => 'Escola Piloto',
        '--domain' => 'piloto.local',
        '--admin-name' => 'Admin Piloto',
        '--admin-email' => 'admin@piloto.local',
        '--admin-password' => 'senha-forte-123',
    ];

    $this->artisan('tenant:provision', $args)->assertExitCode(0);
    $this->artisan('tenant:provision', $args)->assertExitCode(0);

    expect(Tenant::query()->where('domain', 'piloto.local')->count())->toBe(1)
        ->and(User::query()->where('email', 'admin@piloto.local')->count())->toBe(1);
});

it('provisions resolver-ready cash gateway preset for first admin', function (): void {
    $this->artisan('tenant:provision', [
        '--name' => 'Escola Dinheiro',
        '--domain' => 'dinheiro.local',
        '--admin-name' => 'Admin Dinheiro',
        '--admin-email' => 'admin@dinheiro.local',
        '--admin-password' => 'senha-forte-123',
    ])->assertExitCode(0);

    $tenant = Tenant::query()->where('domain', 'dinheiro.local')->firstOrFail();
    $admin = User::query()->where('email', 'admin@dinheiro.local')->firstOrFail();
    $plugin = Plugin::query()->where('slug', 'cash')->firstOrFail();
    $activation = PluginActivation::query()->where('tenant_id', $tenant->id)->where('plugin_id', $plugin->id)->firstOrFail();
    $config = TenantPluginConfig::query()->where('tenant_id', $tenant->id)->where('plugin_id', $plugin->id)->firstOrFail();
    $rawConfig = DB::table('tenant_plugin_configs')->where('id', $config->id)->value('config');

    expect($plugin->name)->toBe('Dinheiro')
        ->and($plugin->capability_key)->toBe('gateway.cash')
        ->and($plugin->kind)->toBe('gateway')
        ->and($plugin->status)->toBe('published')
        ->and($plugin->visibility)->toBe('public')
        ->and($plugin->tier)->toBe('free')
        ->and($plugin->is_curated)->toBeTrue()
        ->and($plugin->directory_name)->toBeNull()
        ->and($plugin->short_description)->toContain('confirmação manual')
        ->and($plugin->long_description)->toContain('confirma')
        ->and($activation->status)->toBe('active')
        ->and($activation->activated_at)->not->toBeNull()
        ->and($activation->activated_by)->toBe($admin->id)
        ->and($config->enabled)->toBeTrue()
        ->and($config->config)->toBe([])
        ->and($config->credentials())->toBe([])
        ->and($rawConfig)->toBeString()
        ->and($rawConfig)->not->toBe('[]');

    $gateway = app(TenantGatewayProvider::class)->activeFor($tenant);

    expect($gateway)->not->toBeNull()
        ->and($gateway->slug)->toBe('cash')
        ->and($gateway->credentials)->toBe([]);

    $resolvedGateway = app(TenantGatewayResolver::class)->resolve($tenant);

    expect($resolvedGateway->adapter)->toBeInstanceOf(CashPaymentGateway::class)
        ->and($resolvedGateway->adapter->identifier())->toBe('cash')
        ->and($resolvedGateway->credentials)->toBe([]);
});

it('does not duplicate or overwrite cash gateway preset choices on re-run', function (): void {
    $args = [
        '--name' => 'Escola Dinheiro',
        '--domain' => 'dinheiro.local',
        '--admin-name' => 'Admin Dinheiro',
        '--admin-email' => 'admin@dinheiro.local',
        '--admin-password' => 'senha-forte-123',
    ];

    $this->artisan('tenant:provision', $args)->assertExitCode(0);

    $tenant = Tenant::query()->where('domain', 'dinheiro.local')->firstOrFail();
    $plugin = Plugin::query()->where('slug', 'cash')->firstOrFail();
    $activation = PluginActivation::query()->where('tenant_id', $tenant->id)->where('plugin_id', $plugin->id)->firstOrFail();
    $config = TenantPluginConfig::query()->where('tenant_id', $tenant->id)->where('plugin_id', $plugin->id)->firstOrFail();

    $activation->update(['status' => 'inactive', 'deactivated_at' => now()]);
    $config->update(['enabled' => false, 'config' => ['instructions' => 'Conferir no caixa']]);

    $this->artisan('tenant:provision', $args)->assertExitCode(0);

    expect(Plugin::query()->where('slug', 'cash')->count())->toBe(1)
        ->and(PluginActivation::query()->where('tenant_id', $tenant->id)->where('plugin_id', $plugin->id)->count())->toBe(1)
        ->and(TenantPluginConfig::query()->where('tenant_id', $tenant->id)->where('plugin_id', $plugin->id)->count())->toBe(1)
        ->and($activation->fresh()->status)->toBe('inactive')
        ->and($config->fresh()->enabled)->toBeFalse()
        ->and($config->fresh()->config)->toBe(['instructions' => 'Conferir no caixa']);
});

it('generates a password when none is provided', function (): void {
    $this->artisan('tenant:provision', [
        '--name' => 'Escola X',
        '--domain' => 'x.local',
        '--admin-name' => 'Admin X',
        '--admin-email' => 'admin@x.local',
    ])->assertExitCode(0);

    expect(User::query()->where('email', 'admin@x.local')->exists())->toBeTrue();
});

it('does not overwrite an existing admin password on re-run', function (): void {
    $base = [
        '--name' => 'Escola',
        '--domain' => 'e.local',
        '--admin-name' => 'A',
        '--admin-email' => 'a@e.local',
    ];

    $this->artisan('tenant:provision', [...$base, '--admin-password' => 'primeira-senha-123'])->assertExitCode(0);
    $this->artisan('tenant:provision', [...$base, '--admin-password' => 'segunda-senha-456'])->assertExitCode(0);

    $admin = User::query()->where('email', 'a@e.local')->firstOrFail();
    expect(Hash::check('primeira-senha-123', $admin->password))->toBeTrue()
        ->and(Hash::check('segunda-senha-456', $admin->password))->toBeFalse();
});

it('reuses an existing tenant resolved by domain', function (): void {
    $existing = Tenant::factory()->create(['domain' => 'reuse.local', 'name' => 'Original']);

    $this->artisan('tenant:provision', [
        '--name' => 'Nome Ignorado',
        '--domain' => 'reuse.local',
        '--admin-name' => 'Admin',
        '--admin-email' => 'admin@reuse.local',
        '--admin-password' => 'senha-forte-123',
    ])->assertExitCode(0);

    expect(Tenant::query()->where('domain', 'reuse.local')->count())->toBe(1);

    $admin = User::query()->where('email', 'admin@reuse.local')->firstOrFail();
    expect($admin->tenant_id)->toBe($existing->id);
});

it('fails when required options are missing', function (): void {
    $this->artisan('tenant:provision', ['--name' => 'Escola'])->assertExitCode(1);
});

it('rejects a weak admin password (min 8)', function (): void {
    $this->artisan('tenant:provision', [
        '--name' => 'Escola',
        '--domain' => 'weak.local',
        '--admin-name' => 'A',
        '--admin-email' => 'a@weak.local',
        '--admin-password' => 'short',
    ])->assertExitCode(1);

    expect(User::query()->where('email', 'a@weak.local')->exists())->toBeFalse()
        ->and(Tenant::query()->where('domain', 'weak.local')->exists())->toBeFalse();
});

it('refuses to silently promote an existing non-admin user to admin', function (): void {
    seedRbac();
    $tenant = Tenant::factory()->create(['domain' => 'prom.local']);
    $student = User::factory()->forTenant($tenant)->student()->create(['email' => 'aluno@prom.local']);

    $this->artisan('tenant:provision', [
        '--name' => 'Prom',
        '--domain' => 'prom.local',
        '--admin-name' => 'X',
        '--admin-email' => 'aluno@prom.local',
        '--admin-password' => 'senha-forte-123',
    ])->assertExitCode(1);

    expect($student->fresh()->user_type)->toBe(UserType::Student);
});

it('promotes an existing non-admin user only with --promote', function (): void {
    seedRbac();
    $tenant = Tenant::factory()->create(['domain' => 'prom.local']);
    $student = User::factory()->forTenant($tenant)->student()->create(['email' => 'aluno@prom.local']);

    $this->artisan('tenant:provision', [
        '--name' => 'Prom',
        '--domain' => 'prom.local',
        '--admin-name' => 'X',
        '--admin-email' => 'aluno@prom.local',
        '--admin-password' => 'senha-forte-123',
        '--promote' => true,
    ])->assertExitCode(0);

    $fresh = $student->fresh();
    expect($fresh->user_type)->toBe(UserType::Admin)
        ->and($fresh->hasRole('admin'))->toBeTrue();
});
