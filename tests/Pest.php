<?php

use App\Enums\UserType;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\PermissionsSeeder;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| Todo teste de Feature usa RefreshDatabase por padrão (banco `testing`,
| conexão mysql via phpunit.xml). Não repita `uses(RefreshDatabase::class)`
| nos arquivos — já está aplicado aqui.
|
*/

pest()->extend(Tests\TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Harness Helpers (ver skill pest-api-tests)
|--------------------------------------------------------------------------
|
| Cortam o boilerplate de ~40 linhas por teste (criar tenant, seed RBAC,
| criar user+role, token, header X-Tenant-ID) para 1-2 linhas.
|
*/

/**
 * Semeia permissions e roles canônicas para o teste atual.
 */
function seedRbac(): void
{
    test()->seed([PermissionsSeeder::class, RolesSeeder::class]);
}

/**
 * Cria um tenant ativo para o teste.
 *
 * @param  array<string, mixed>  $overrides
 */
function makeTenant(array $overrides = []): Tenant
{
    return Tenant::factory()->create($overrides);
}

/**
 * Monta os headers de uma requisição tenant-scoped (X-Tenant-ID + Bearer opcional).
 *
 * @return array<string, string>
 */
function tenantHeaders(Tenant $tenant, ?string $token = null): array
{
    $headers = ['X-Tenant-ID' => (string) $tenant->id];

    if ($token !== null) {
        $headers['Authorization'] = 'Bearer '.$token;
    }

    return $headers;
}

/**
 * Cria um usuário do UserType informado (com role e token Sanctum) e devolve
 * o par [usuário, headers]. Developer é global (sem tenant); os demais são
 * tenant-scoped (cria um tenant se nenhum for passado).
 *
 * @return array{0: User, 1: array<string, string>}
 */
function actingAsUserType(UserType $type, ?Tenant $tenant = null): array
{
    seedRbac();

    $builder = match ($type) {
        UserType::Developer => User::factory()->developer(),
        UserType::Admin => User::factory()->admin(),
        UserType::Instructor => User::factory()->instructor(),
        UserType::Student => User::factory()->student(),
    };

    if ($type !== UserType::Developer) {
        $tenant ??= makeTenant();
        $builder = $builder->forTenant($tenant);
    }

    $user = $builder->create();
    $user->assignRole($type->value);

    $token = $user->createToken('test')->plainTextToken;

    $headers = $type === UserType::Developer
        ? ['Authorization' => 'Bearer '.$token]
        : tenantHeaders($tenant, $token);

    return [$user, $headers];
}

/*
|--------------------------------------------------------------------------
| Expectations / Assertions
|--------------------------------------------------------------------------
*/

/**
 * Asserta o envelope de erro padrão da API: {data:null, errors:[{code,message}]}.
 */
function assertApiErrorEnvelope(TestResponse $response, int $status, ?string $code = null): TestResponse
{
    $response->assertStatus($status)
        ->assertJsonPath('data', null)
        ->assertJsonStructure(['data', 'errors' => [['code', 'message']]]);

    if ($code !== null) {
        $response->assertJsonPath('errors.0.code', $code);
    }

    return $response;
}

/**
 * Asserta que uma resposta de acesso cross-tenant foi negada (403 ou 404).
 */
function assertTenantIsolation(TestResponse $response): TestResponse
{
    expect($response->status())->toBeIn([403, 404]);

    return $response;
}
