<?php

use App\Modules\Core\Enums\UserType;
use App\Modules\Core\Models\Tenant;
use App\Modules\Core\Models\User;
use Database\Seeders\PermissionsSeeder;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\PersonalAccessToken;

uses(RefreshDatabase::class);

/**
 * @return array{0: Tenant, 1: User, 2: array<string, string>}
 */
function adminUserManagementContext(UserType $actingType = UserType::Admin, bool $withRole = true): array
{
    $tenant = Tenant::factory()->create();
    test()->seed([PermissionsSeeder::class, RolesSeeder::class]);

    $actor = User::factory()->create([
        'tenant_id' => $actingType === UserType::Developer ? null : $tenant->id,
        'user_type' => $actingType,
    ]);

    if ($withRole) {
        $actor->assignRole($actingType->value);
    }

    return [$tenant, $actor, [
        'Authorization' => 'Bearer '.$actor->createToken('actor-token')->plainTextToken,
        'X-Tenant-ID' => (string) $tenant->id,
    ]];
}

it('updates profile fields of a tenant student', function (): void {
    [$tenant, , $headers] = adminUserManagementContext();

    $student = User::factory()->create(['tenant_id' => $tenant->id, 'user_type' => UserType::Student]);

    $this->patchJson('/api/v1/admin/users/'.$student->id, [
        'name' => 'Nome Corrigido',
        'headline' => 'Aluna dedicada',
    ], $headers)
        ->assertSuccessful()
        ->assertJsonPath('data.id', $student->id)
        ->assertJsonPath('data.name', 'Nome Corrigido');

    expect($student->fresh()->name)->toBe('Nome Corrigido')
        ->and($student->fresh()->headline)->toBe('Aluna dedicada');
});

it('updates a tenant instructor', function (): void {
    [$tenant, , $headers] = adminUserManagementContext();

    $instructor = User::factory()->create(['tenant_id' => $tenant->id, 'user_type' => UserType::Instructor]);

    $this->patchJson('/api/v1/admin/users/'.$instructor->id, [
        'bio' => 'Trabalha com design há 8 anos.',
    ], $headers)->assertSuccessful();

    expect($instructor->fresh()->bio)->toBe('Trabalha com design há 8 anos.');
});

it('rejects restricted fields in the payload', function (string $field, mixed $value): void {
    [$tenant, , $headers] = adminUserManagementContext();

    $student = User::factory()->create(['tenant_id' => $tenant->id, 'user_type' => UserType::Student]);
    $before = $student->only(['user_type', 'email', 'cpf', 'password']);

    $this->patchJson('/api/v1/admin/users/'.$student->id, [$field => $value], $headers)
        ->assertUnprocessable()
        ->assertJsonPath('errors.0.code', 'validation_error');

    expect($student->fresh()->only(['user_type', 'email', 'cpf', 'password']))->toEqual($before);
})->with([
    'user_type' => ['user_type', 'admin'],
    'email' => ['email', 'novo@tenant.local'],
    'cpf' => ['cpf', '123.456.789-00'],
    'password' => ['password', 'nova-senha-123'],
]);

it('hides another tenant admin from the admin user surface', function (): void {
    [$tenant, , $headers] = adminUserManagementContext();

    $otherAdmin = User::factory()->create(['tenant_id' => $tenant->id, 'user_type' => UserType::Admin]);

    $this->patchJson('/api/v1/admin/users/'.$otherAdmin->id, [
        'name' => 'Escalada Lateral',
    ], $headers)
        ->assertForbidden()
        ->assertJsonPath('errors.0.code', 'access_denied');

    expect($otherAdmin->fresh()->name)->not->toBe('Escalada Lateral');
});

it('hides a developer as not found', function (): void {
    [, , $headers] = adminUserManagementContext();

    $developer = User::factory()->create(['tenant_id' => null, 'user_type' => UserType::Developer]);

    $this->patchJson('/api/v1/admin/users/'.$developer->id, [
        'name' => 'Tentativa',
    ], $headers)
        ->assertNotFound()
        ->assertJsonPath('errors.0.code', 'not_found');

    expect($developer->fresh()->name)->not->toBe('Tentativa');
});

it('hides a user from another tenant as not found', function (): void {
    [, , $headers] = adminUserManagementContext();

    $foreignStudent = User::factory()->create([
        'tenant_id' => Tenant::factory()->create()->id,
        'user_type' => UserType::Student,
    ]);

    $this->patchJson('/api/v1/admin/users/'.$foreignStudent->id, [
        'name' => 'Tentativa Cross Tenant',
    ], $headers)
        ->assertNotFound()
        ->assertJsonPath('errors.0.code', 'not_found');

    expect($foreignStudent->fresh()->name)->not->toBe('Tentativa Cross Tenant');
});

it('forbids the admin from managing themselves through this surface', function (): void {
    [, $admin, $headers] = adminUserManagementContext();

    $this->patchJson('/api/v1/admin/users/'.$admin->id, [
        'name' => 'Auto Edição',
    ], $headers)->assertForbidden();

    expect($admin->fresh()->name)->not->toBe('Auto Edição');
});

it('forbids non admin personas from the admin user surface', function (UserType $userType): void {
    [$tenant, , $headers] = adminUserManagementContext($userType);

    $student = User::factory()->create(['tenant_id' => $tenant->id, 'user_type' => UserType::Student]);

    $this->patchJson('/api/v1/admin/users/'.$student->id, [
        'name' => 'Fora da Área',
    ], $headers)
        ->assertForbidden()
        ->assertJsonPath('errors.0.code', 'area_forbidden');

    expect($student->fresh()->name)->not->toBe('Fora da Área');
})->with([
    'developer' => [UserType::Developer],
    'instructor' => [UserType::Instructor],
    'student' => [UserType::Student],
]);

it('forbids an admin without the update permission', function (): void {
    [$tenant, , $headers] = adminUserManagementContext(UserType::Admin, withRole: false);

    $student = User::factory()->create(['tenant_id' => $tenant->id, 'user_type' => UserType::Student]);

    $this->patchJson('/api/v1/admin/users/'.$student->id, [
        'name' => 'Sem Permission',
    ], $headers)
        ->assertForbidden()
        ->assertJsonPath('errors.0.code', 'access_denied');

    expect($student->fresh()->name)->not->toBe('Sem Permission');
});

it('soft deletes a tenant student and revokes its sessions', function (): void {
    [$tenant, , $headers] = adminUserManagementContext();

    $student = User::factory()->create(['tenant_id' => $tenant->id, 'user_type' => UserType::Student]);
    $studentToken = $student->createToken('student-token')->plainTextToken;

    $this->deleteJson('/api/v1/admin/users/'.$student->id, [], $headers)
        ->assertSuccessful()
        ->assertJsonPath('data.message', 'User deleted successfully.')
        ->assertJsonMissingPath('message');

    expect(User::query()->find($student->id))->toBeNull()
        ->and(User::withTrashed()->find($student->id)?->deleted_at)->not->toBeNull()
        ->and(PersonalAccessToken::query()->where('tokenable_id', $student->id)->count())->toBe(0);

    // O guard mantém o usuário resolvido do request anterior dentro do mesmo teste.
    $this->app['auth']->forgetGuards();

    $this->getJson('/api/v1/core/auth/me', [
        'Authorization' => 'Bearer '.$studentToken,
        'X-Tenant-ID' => (string) $tenant->id,
    ])->assertUnauthorized();
});

it('blocks login for a soft deleted user', function (): void {
    [$tenant, , $headers] = adminUserManagementContext();

    $student = User::factory()->create([
        'tenant_id' => $tenant->id,
        'user_type' => UserType::Student,
        'email' => 'aluno-removido@tenant.local',
        'password' => bcrypt('password123'),
    ]);

    $this->deleteJson('/api/v1/admin/users/'.$student->id, [], $headers)->assertSuccessful();

    $this->postJson('/api/v1/core/auth/login', [
        'email' => 'aluno-removido@tenant.local',
        'password' => 'password123',
    ], ['X-Tenant-ID' => (string) $tenant->id])
        ->assertUnauthorized()
        ->assertJsonPath('errors.0.code', 'invalid_credentials');
});

it('hides a soft deleted user from the admin surface', function (): void {
    [$tenant, , $headers] = adminUserManagementContext();

    $student = User::factory()->create(['tenant_id' => $tenant->id, 'user_type' => UserType::Student]);
    $student->delete();

    $this->patchJson('/api/v1/admin/users/'.$student->id, ['name' => 'Ressurreição'], $headers)
        ->assertNotFound();

    $this->deleteJson('/api/v1/admin/users/'.$student->id, [], $headers)
        ->assertNotFound();
});

it('forbids deleting another tenant admin', function (): void {
    [$tenant, , $headers] = adminUserManagementContext();

    $otherAdmin = User::factory()->create(['tenant_id' => $tenant->id, 'user_type' => UserType::Admin]);

    $this->deleteJson('/api/v1/admin/users/'.$otherAdmin->id, [], $headers)
        ->assertForbidden()
        ->assertJsonPath('errors.0.code', 'access_denied');

    expect(User::query()->find($otherAdmin->id))->not->toBeNull();
});

it('lists users through the canonical admin surface within the active tenant', function (): void {
    [$tenant, , $headers] = adminUserManagementContext();
    $otherTenant = Tenant::factory()->create();

    $ownStudent = User::factory()->create([
        'tenant_id' => $tenant->id,
        'user_type' => UserType::Student,
        'email' => 'own-student@tenant-a.local',
    ]);
    $foreignStudent = User::factory()->create([
        'tenant_id' => $otherTenant->id,
        'user_type' => UserType::Student,
        'email' => 'foreign-student@tenant-b.local',
    ]);

    $this->getJson('/api/v1/admin/users', $headers)
        ->assertSuccessful()
        ->assertJsonFragment(['email' => $ownStudent->email])
        ->assertJsonMissing(['email' => $foreignStudent->email]);
});

it('shows a user through the canonical admin surface and hides another tenant', function (): void {
    [$tenant, , $headers] = adminUserManagementContext();
    $otherTenant = Tenant::factory()->create();

    $ownStudent = User::factory()->create([
        'tenant_id' => $tenant->id,
        'user_type' => UserType::Student,
    ]);
    $foreignStudent = User::factory()->create([
        'tenant_id' => $otherTenant->id,
        'user_type' => UserType::Student,
    ]);

    $this->getJson('/api/v1/admin/users/'.$ownStudent->id, $headers)
        ->assertSuccessful()
        ->assertJsonPath('data.id', $ownStudent->id);

    assertApiErrorEnvelope(
        $this->getJson('/api/v1/admin/users/'.$foreignStudent->id, $headers),
        404,
        'not_found',
    );
});

it('denies the canonical admin user list by area before permission checks', function (): void {
    [, $headers] = actingAsUserType(UserType::Instructor);

    assertApiErrorEnvelope(
        $this->getJson('/api/v1/admin/users', $headers),
        403,
        'area_forbidden',
    );
});
