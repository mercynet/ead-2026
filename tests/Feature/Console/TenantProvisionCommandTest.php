<?php

use App\Modules\Core\Enums\UserType;
use App\Modules\Core\Models\Tenant;
use App\Modules\Core\Models\User;
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
