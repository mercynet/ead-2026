<?php

use App\Modules\Core\Actions\Tenants\ProvisionTenantAction;
use App\Modules\Core\Enums\UserType;
use App\Modules\Core\Models\Tenant;
use App\Modules\Core\Models\User;
use App\Modules\Ecosystem\Models\Plugin;
use App\Modules\Ecosystem\Models\PluginActivation;
use App\Modules\Ecosystem\Models\TenantPluginConfig;
use App\Shared\Contracts\TenantProvisioningParticipant;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Exceptions\RoleDoesNotExist;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

function createMzrtTenant(array $payload, array $headers = []): \Illuminate\Testing\TestResponse
{
    return test()->postJson('/api/v1/mzrt/tenants', $payload, $headers);
}

function mzrtTenantPayload(array $overrides = []): array
{
    return array_replace_recursive([
        'name' => 'Escola MZRT',
        'domain' => 'mzrt.local',
        'database' => 'tenant_mzrt',
        'description' => 'Tenant provisionado pela MZRT.',
        'admin' => [
            'name' => 'Admin MZRT',
            'email' => 'admin@mzrt.local',
            'password' => 'senha-forte-123',
            'cpf' => '12345678901',
        ],
    ], $overrides);
}

it('creates an active tenant and first admin without a tenant header', function (): void {
    [, $headers] = actingAsUserType(UserType::Developer);

    $response = createMzrtTenant(mzrtTenantPayload(), $headers);

    $response->assertCreated()
        ->assertJsonStructure([
            'data' => [
                'tenant' => ['id', 'name', 'domain', 'database', 'description', 'status'],
                'admin' => ['id', 'name', 'email', 'user_type'],
            ],
        ])
        ->assertJsonMissingPath('data.admin.cpf')
        ->assertJsonMissingPath('data.admin.password');

    $tenant = Tenant::query()->where('domain', 'mzrt.local')->firstOrFail();
    $admin = User::query()->where('tenant_id', $tenant->id)->where('email', 'admin@mzrt.local')->firstOrFail();
    $cashPlugin = Plugin::query()->where('slug', 'cash')->firstOrFail();
    $activation = PluginActivation::query()->where('tenant_id', $tenant->id)->where('plugin_id', $cashPlugin->id)->firstOrFail();
    $config = TenantPluginConfig::query()->where('tenant_id', $tenant->id)->where('plugin_id', $cashPlugin->id)->firstOrFail();

    expect($tenant->is_active)->toBeTrue()
        ->and($tenant->database)->toBe('tenant_mzrt')
        ->and($tenant->description)->toBe('Tenant provisionado pela MZRT.')
        ->and($admin->user_type)->toBe(UserType::Admin)
        ->and($admin->hasRole('admin'))->toBeTrue()
        ->and(Hash::check('senha-forte-123', $admin->password))->toBeTrue()
        ->and($cashPlugin->capability_key)->toBe('gateway.cash')
        ->and($activation->status)->toBe('active')
        ->and($activation->activated_by)->toBe($admin->id)
        ->and($config->enabled)->toBeTrue()
        ->and(array_keys($response->json('data')))->toBe(['tenant', 'admin'])
        ->and(array_keys($response->json('data.tenant')))->toBe(['id', 'name', 'domain', 'database', 'description', 'status'])
        ->and(array_keys($response->json('data.admin')))->toBe(['id', 'name', 'email', 'user_type']);
});

it('does not scope tenant creation from an unrelated tenant header', function (): void {
    [, $headers] = actingAsUserType(UserType::Developer);
    $unrelatedTenant = makeTenant();
    $headers['X-Tenant-ID'] = (string) $unrelatedTenant->id;

    createMzrtTenant(mzrtTenantPayload(), $headers)->assertCreated();

    $tenant = Tenant::query()->where('domain', 'mzrt.local')->firstOrFail();
    expect($tenant->id)->not->toBe($unrelatedTenant->id);
});

it('requires authentication', function (): void {
    assertApiErrorEnvelope(createMzrtTenant(mzrtTenantPayload()), 401, 'unauthenticated');
});

it('denies non-developers before request validation', function (): void {
    [, $headers] = actingAsUserType(UserType::Admin);

    assertApiErrorEnvelope(createMzrtTenant([], $headers), 403, 'area_forbidden');
});

it('denies developers without create tenant permission', function (): void {
    $developer = User::factory()->developer()->create();
    $headers = ['Authorization' => 'Bearer '.$developer->createToken('without-role')->plainTextToken];

    assertApiErrorEnvelope(createMzrtTenant(mzrtTenantPayload(), $headers), 403, 'access_denied');
});

it('validates nested tenant and admin fields', function (): void {
    [, $headers] = actingAsUserType(UserType::Developer);

    assertApiErrorEnvelope(createMzrtTenant([
        'name' => '',
        'domain' => '',
        'admin' => ['name' => '', 'email' => 'invalid', 'password' => 'short'],
    ], $headers), 422, 'validation_error');

    assertApiErrorEnvelope(createMzrtTenant([
        'name' => 'Escola',
        'domain' => 'missing-password.local',
        'admin' => ['name' => 'Admin', 'email' => 'admin@missing-password.local'],
    ], $headers), 422, 'validation_error');
});

it('returns duplicate domain conflict without changing existing tenant or creating an admin', function (): void {
    [, $headers] = actingAsUserType(UserType::Developer);
    $existing = makeTenant(['domain' => 'mzrt.local', 'name' => 'Original']);

    assertApiErrorEnvelope(createMzrtTenant(mzrtTenantPayload(), $headers), 409, 'tenant_already_exists')
        ->assertJsonPath('errors.0.message', 'Não foi possível criar o tenant.')
        ->assertJsonMissingPath('errors.0.domain');

    expect($existing->fresh()->name)->toBe('Original')
        ->and(User::query()->where('tenant_id', $existing->id)->count())->toBe(0);
});

it('maps a post-lookup domain unique collision to the tenant conflict envelope', function (): void {
    [, $headers] = actingAsUserType(UserType::Developer);
    $dispatcher = Tenant::getEventDispatcher();
    $database = Mockery::mock(DatabaseManager::class);
    $database->shouldReceive('transaction')->once()->andReturnUsing(
        fn (Closure $callback) => $callback(),
    );
    $this->app->instance(ProvisionTenantAction::class, new ProvisionTenantAction(
        $database,
        new class implements TenantProvisioningParticipant
        {
            public function provision(int $tenantId, int $adminUserId): void {}
        },
    ));

    Tenant::creating(function (Tenant $tenant): never {
        Tenant::withoutEvents(function () use ($tenant): void {
            Tenant::query()->create([
                'name' => 'Concurrente',
                'domain' => $tenant->domain,
                'is_active' => true,
            ]);
        });

        throw new UniqueConstraintViolationException(
            'mysql',
            'insert into `tenants` (`domain`) values (?)',
            [$tenant->domain],
            new \PDOException('Duplicate entry'),
            [],
            null,
        );
    });

    try {
        assertApiErrorEnvelope(createMzrtTenant(mzrtTenantPayload(), $headers), 409, 'tenant_already_exists');

        expect(Tenant::query()->where('domain', 'mzrt.local')->count())->toBe(1)
            ->and(User::query()->where('email', 'admin@mzrt.local')->exists())->toBeFalse();
    } finally {
        Tenant::setEventDispatcher($dispatcher);
    }
});

it('rolls back tenant creation when admin creation fails', function (): void {
    [, $headers] = actingAsUserType(UserType::Developer);
    $dispatcher = User::getEventDispatcher();
    User::creating(function (): void {
        throw new RuntimeException('Admin creation failed.');
    });

    try {
        $this->withoutExceptionHandling();

        expect(fn () => createMzrtTenant(mzrtTenantPayload(), $headers))
            ->toThrow(RuntimeException::class, 'Admin creation failed.');

        expect(Tenant::query()->where('domain', 'mzrt.local')->exists())->toBeFalse()
            ->and(User::query()->where('email', 'admin@mzrt.local')->exists())->toBeFalse();
    } finally {
        User::setEventDispatcher($dispatcher);
    }
});

it('rolls back tenant and admin when assigning admin role fails', function (): void {
    [, $headers] = actingAsUserType(UserType::Developer);
    Role::findByName(UserType::Admin->value)->delete();
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    try {
        $this->withoutExceptionHandling();

        expect(fn () => createMzrtTenant(mzrtTenantPayload(), $headers))
            ->toThrow(RoleDoesNotExist::class);

        expect(Tenant::query()->where('domain', 'mzrt.local')->exists())->toBeFalse()
            ->and(User::query()->where('email', 'admin@mzrt.local')->exists())->toBeFalse();
    } finally {
        seedRbac();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
});

it('rolls back all provisioning artifacts when a participant fails', function (): void {
    [, $headers] = actingAsUserType(UserType::Developer);
    $adminRole = Role::findByName(UserType::Admin->value);
    $roleAssignmentsBefore = DB::table('model_has_roles')->where('role_id', $adminRole->id)->count();

    $this->app->instance(TenantProvisioningParticipant::class, new class implements TenantProvisioningParticipant
    {
        public function provision(int $tenantId, int $adminUserId): void
        {
            $cashPlugin = Plugin::query()->firstOrCreate(
                ['slug' => 'cash'],
                [
                    'name' => 'Dinheiro',
                    'capability_key' => 'gateway.cash',
                    'kind' => 'gateway',
                    'status' => 'published',
                    'visibility' => 'public',
                    'tier' => 'free',
                    'is_curated' => true,
                ],
            );

            PluginActivation::query()->create([
                'tenant_id' => $tenantId,
                'plugin_id' => $cashPlugin->id,
                'status' => 'active',
                'activated_at' => now(),
                'activated_by' => $adminUserId,
            ]);

            throw new RuntimeException('Participant failed.');
        }
    });

    $this->withoutExceptionHandling();

    expect(fn () => createMzrtTenant(mzrtTenantPayload(), $headers))
        ->toThrow(RuntimeException::class, 'Participant failed.');

    expect(Tenant::query()->where('domain', 'mzrt.local')->exists())->toBeFalse()
        ->and(User::query()->where('email', 'admin@mzrt.local')->exists())->toBeFalse()
        ->and(DB::table('model_has_roles')->where('role_id', $adminRole->id)->count())->toBe($roleAssignmentsBefore)
        ->and(Plugin::query()->where('slug', 'cash')->exists())->toBeFalse()
        ->and(PluginActivation::query()->exists())->toBeFalse()
        ->and(TenantPluginConfig::query()->exists())->toBeFalse();
});
