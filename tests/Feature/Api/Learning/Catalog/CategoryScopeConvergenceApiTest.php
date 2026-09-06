<?php

use App\Modules\Core\Enums\UserType;
use App\Modules\Learning\Models\Category;
use Illuminate\Support\Facades\Schema;

it('exposes the canonical System and Custom vocabulary and materialized root fields', function (): void {
    [, $developerHeaders] = actingAsUserType(UserType::Developer);

    $systemResponse = $this->postJson('/api/v1/mzrt/categories', [
        'name' => '  Dados  Abertos  ',
    ], $developerHeaders);

    $systemResponse
        ->assertCreated()
        ->assertJsonPath('data.type', 'system')
        ->assertJsonPath('data.path', '/'.$systemResponse->json('data.id'))
        ->assertJsonPath('data.depth', 0)
        ->assertJsonMissingPath('data.is_system');

});

it('exposes Custom vocabulary and canonical fields for tenant categories', function (): void {
    [$admin, $headers] = actingAsUserType(UserType::Admin);

    $customResponse = $this->postJson('/api/v1/admin/categories', [
        'name' => '  Gestão   de Projetos  ',
    ], $headers);

    $customResponse
        ->assertCreated()
        ->assertJsonPath('data.type', 'custom')
        ->assertJsonPath('data.path', '/'.$customResponse->json('data.id'))
        ->assertJsonPath('data.depth', 0)
        ->assertJsonMissingPath('data.is_system');

    expect(Category::query()->findOrFail($customResponse->json('data.id'))->normalized_name)
        ->toBe('gestao de projetos');
});

it('rejects semantic duplicates in the same custom tenant regardless of parent', function (): void {
    [$admin, $headers] = actingAsUserType(UserType::Admin);
    $tenant = $admin->tenant;

    $firstParent = Category::factory()->for($tenant)->create(['name' => 'Primeiro Pai']);
    $secondParent = Category::factory()->for($tenant)->create(['name' => 'Segundo Pai']);

    $this->postJson('/api/v1/admin/categories', [
        'name' => '  Relatórios   Mensais ',
        'parent_id' => $firstParent->id,
    ], $headers)->assertCreated();

    assertApiErrorEnvelope(
        $this->postJson('/api/v1/admin/categories', [
            'name' => 'Relatórios Mensais',
            'parent_id' => $secondParent->id,
        ], $headers),
        422,
        'validation_error',
    );
});

it('rejects a custom category under a system parent', function (): void {
    [$admin, $headers] = actingAsUserType(UserType::Admin);
    $systemParent = Category::factory()->system()->create(['name' => 'Taxonomia Global']);

    assertApiErrorEnvelope(
        $this->postJson('/api/v1/admin/categories', [
            'name' => 'Filha Custom',
            'parent_id' => $systemParent->id,
        ], $headers),
        422,
        'validation_error',
    );
});

it('maintains path and depth when moving a subtree and prevents cycles', function (): void {
    [$admin, $headers] = actingAsUserType(UserType::Admin);

    $root = $this->postJson('/api/v1/admin/categories', ['name' => 'Raiz'], $headers)
        ->assertCreated()
        ->json('data');
    $secondRoot = $this->postJson('/api/v1/admin/categories', ['name' => 'Outra Raiz'], $headers)
        ->assertCreated()
        ->json('data');
    $child = $this->postJson('/api/v1/admin/categories', [
        'name' => 'Filha',
        'parent_id' => $root['id'],
    ], $headers)->assertCreated()->json('data');
    $grandchild = $this->postJson('/api/v1/admin/categories', [
        'name' => 'Neta',
        'parent_id' => $child['id'],
    ], $headers)->assertCreated()->json('data');

    $this->putJson('/api/v1/admin/categories/'.$child['id'], [
        'parent_id' => $secondRoot['id'],
    ], $headers)
        ->assertOk()
        ->assertJsonPath('data.path', '/'.$secondRoot['id'].'/'.$child['id'])
        ->assertJsonPath('data.depth', 1);

    expect(Category::query()->findOrFail($grandchild['id']))
        ->path->toBe('/'.$secondRoot['id'].'/'.$child['id'].'/'.$grandchild['id'])
        ->depth->toBe(2);

    assertApiErrorEnvelope(
        $this->putJson('/api/v1/admin/categories/'.$secondRoot['id'], [
            'parent_id' => $grandchild['id'],
        ], $headers),
        422,
        'validation_error',
    );
});

it('has the database columns for scoped uniqueness and materialized paths', function (): void {
    expect(Schema::hasColumns('categories', [
        'tenant_key',
        'path',
        'depth',
    ]))->toBeTrue();
});
