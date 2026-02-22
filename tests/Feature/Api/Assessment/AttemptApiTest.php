<?php

use App\Enums\UserType;
use App\Models\Questionnaire;
use App\Models\QuizAttempt;
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

    $this->questionnaire = Questionnaire::factory()->create([
        'tenant_id' => $this->tenant->id,
    ]);
});

it('starts an attempt', function (): void {
    Sanctum::actingAs($this->developer);

    $response = $this->postJson(
        "/api/v1/assessment/attempts/questionnaires/{$this->questionnaire->id}",
        [],
        ['X-Tenant-ID' => (string) $this->tenant->id],
    );

    $response->assertSuccessful();
    $response->assertJsonStructure([
        'data' => ['id', 'questionnaire_id', 'status', 'started_at'],
    ]);

    expect(QuizAttempt::query()->where('questionnaire_id', $this->questionnaire->id)->exists())->toBeTrue();
});

it('shows an attempt', function (): void {
    Sanctum::actingAs($this->developer);

    $attempt = QuizAttempt::factory()->create([
        'tenant_id' => $this->tenant->id,
        'user_id' => $this->developer->id,
        'questionnaire_id' => $this->questionnaire->id,
    ]);

    $response = $this->getJson(
        "/api/v1/assessment/attempts/{$attempt->id}",
        ['X-Tenant-ID' => (string) $this->tenant->id],
    );

    $response->assertSuccessful();
    $response->assertJsonPath('data.id', $attempt->id);
});

it('submits an answer', function (): void {
    Sanctum::actingAs($this->developer);

    $attempt = QuizAttempt::factory()->create([
        'tenant_id' => $this->tenant->id,
        'user_id' => $this->developer->id,
        'questionnaire_id' => $this->questionnaire->id,
    ]);

    $response = $this->patchJson(
        "/api/v1/assessment/attempts/{$attempt->id}",
        [
            'question_snapshot' => [
                'question' => 'Test question?',
                'type' => 'single_choice',
                'options' => [
                    ['text' => 'Option A', 'correct' => true],
                    ['text' => 'Option B', 'correct' => false],
                ],
                'correct_options' => [0],
                'points' => 1,
            ],
            'selected_options' => [0],
        ],
        ['X-Tenant-ID' => (string) $this->tenant->id],
    );

    $response->assertSuccessful();
    $response->assertJsonStructure([
        'data' => ['id', 'quiz_attempt_id', 'is_correct'],
    ]);
});

it('finishes an attempt', function (): void {
    Sanctum::actingAs($this->developer);

    $attempt = QuizAttempt::factory()->create([
        'tenant_id' => $this->tenant->id,
        'user_id' => $this->developer->id,
        'questionnaire_id' => $this->questionnaire->id,
    ]);

    $response = $this->postJson(
        "/api/v1/assessment/attempts/{$attempt->id}/finish",
        [],
        ['X-Tenant-ID' => (string) $this->tenant->id],
    );

    $response->assertSuccessful();
    $response->assertJsonPath('data.status', 'completed');
    $response->assertJsonStructure([
        'data' => ['id', 'status', 'score', 'passed'],
    ]);
});

it('calculates score on finish', function (): void {
    Sanctum::actingAs($this->developer);

    $questionnaire = Questionnaire::factory()->create([
        'tenant_id' => $this->tenant->id,
        'passing_score' => 70,
    ]);

    $attempt = QuizAttempt::factory()->create([
        'tenant_id' => $this->tenant->id,
        'user_id' => $this->developer->id,
        'questionnaire_id' => $questionnaire->id,
        'questionnaire_snapshot' => [
            'title' => $questionnaire->title,
            'passing_score' => $questionnaire->passing_score,
        ],
    ]);

    $response = $this->postJson(
        "/api/v1/assessment/attempts/{$attempt->id}/finish",
        [],
        ['X-Tenant-ID' => (string) $this->tenant->id],
    );

    $response->assertSuccessful();
    $response->assertJsonStructure([
        'data' => ['score', 'passed'],
    ]);
});
