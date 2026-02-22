<?php

use App\Enums\UserType;
use App\Models\Questionnaire;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\PermissionsSeeder;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed([PermissionsSeeder::class, RolesSeeder::class]);

    $this->tenant = Tenant::query()->create([
        'name' => 'Tenant A',
        'domain' => 'tenant-a.local',
        'database' => null,
        'is_active' => true,
    ]);

    $this->developer = User::query()->create([
        'tenant_id' => null,
        'user_type' => UserType::Developer,
        'name' => 'Developer',
        'email' => 'developer@example.com',
        'password' => bcrypt('password'),
    ]);

    $this->developer->assignRole('developer');
});

it('lists questionnaires', function (): void {
    Sanctum::actingAs($this->developer);

    Questionnaire::factory()->count(3)->create([
        'tenant_id' => $this->tenant->id,
    ]);

    $response = $this->getJson('/api/v1/assessment/questionnaires', [
        'X-Tenant-ID' => (string) $this->tenant->id,
    ]);

    $response->assertSuccessful();
    $response->assertJsonStructure([
        'data' => [
            '*' => ['id', 'title', 'type', 'is_active'],
        ],
    ]);
});

it('creates a questionnaire', function (): void {
    Sanctum::actingAs($this->developer);

    $response = $this->postJson(
        '/api/v1/assessment/questionnaires',
        [
            'title' => 'Test Quiz',
            'description' => 'Test description',
            'type' => 'standalone',
            'passing_score' => 70,
        ],
        ['X-Tenant-ID' => (string) $this->tenant->id],
    );

    $response->assertSuccessful();
    $response->assertJsonStructure([
        'data' => ['id', 'title', 'type', 'is_active'],
    ]);

    expect(Questionnaire::query()->where('title', 'Test Quiz')->exists())->toBeTrue();
});

it('shows a questionnaire', function (): void {
    Sanctum::actingAs($this->developer);

    $questionnaire = Questionnaire::factory()->create([
        'tenant_id' => $this->tenant->id,
    ]);

    $response = $this->getJson(
        "/api/v1/assessment/questionnaires/{$questionnaire->id}",
        ['X-Tenant-ID' => (string) $this->tenant->id],
    );

    $response->assertSuccessful();
    $response->assertJsonPath('data.title', $questionnaire->title);
});

it('updates a questionnaire', function (): void {
    Sanctum::actingAs($this->developer);

    $questionnaire = Questionnaire::factory()->create([
        'tenant_id' => $this->tenant->id,
    ]);

    $response = $this->patchJson(
        "/api/v1/assessment/questionnaires/{$questionnaire->id}",
        ['title' => 'Updated Title'],
        ['X-Tenant-ID' => (string) $this->tenant->id],
    );

    $response->assertSuccessful();
    $response->assertJsonPath('data.title', 'Updated Title');
});

it('deletes a questionnaire', function (): void {
    Sanctum::actingAs($this->developer);

    $questionnaire = Questionnaire::factory()->create([
        'tenant_id' => $this->tenant->id,
    ]);

    $response = $this->deleteJson(
        "/api/v1/assessment/questionnaires/{$questionnaire->id}",
        [],
        ['X-Tenant-ID' => (string) $this->tenant->id],
    );

    $response->assertSuccessful();
    expect(Questionnaire::query()->find($questionnaire->id))->toBeNull();
});
