<?php

use App\Enums\UserType;
use App\Models\Questionnaire;
use App\Models\QuestionnaireQuestion;
use App\Models\QuizAttempt;
use App\Models\QuizQuestion;
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

it('lists questions', function (): void {
    Sanctum::actingAs($this->developer);

    QuizQuestion::factory()->count(3)->create([
        'tenant_id' => $this->tenant->id,
    ]);

    $response = $this->getJson('/api/v1/assessment/questions', [
        'X-Tenant-ID' => (string) $this->tenant->id,
    ]);

    $response->assertSuccessful();
    $response->assertJsonStructure([
        'data' => [
            '*' => ['id', 'question', 'type', 'points', 'is_active'],
        ],
    ]);
});

it('creates a question', function (): void {
    Sanctum::actingAs($this->developer);

    $response = $this->postJson(
        '/api/v1/assessment/questions',
        [
            'question' => 'What is Laravel?',
            'type' => 'single_choice',
            'options' => [
                ['text' => 'A framework', 'correct' => true],
                ['text' => 'A language', 'correct' => false],
                ['text' => 'A database', 'correct' => false],
                ['text' => 'An OS', 'correct' => false],
            ],
            'correct_options' => [0],
            'explanation' => 'Laravel is a PHP framework.',
            'points' => 1,
        ],
        ['X-Tenant-ID' => (string) $this->tenant->id],
    );

    $response->assertSuccessful();
    $response->assertJsonStructure([
        'data' => ['id', 'question', 'type', 'is_active'],
    ]);

    expect(QuizQuestion::query()->where('question', 'What is Laravel?')->exists())->toBeTrue();
});

it('shows a question', function (): void {
    Sanctum::actingAs($this->developer);

    $question = QuizQuestion::factory()->create([
        'tenant_id' => $this->tenant->id,
    ]);

    $response = $this->getJson(
        "/api/v1/assessment/questions/{$question->id}",
        ['X-Tenant-ID' => (string) $this->tenant->id],
    );

    $response->assertSuccessful();
    $response->assertJsonPath('data.question', $question->question);
});

it('updates a question', function (): void {
    Sanctum::actingAs($this->developer);

    $question = QuizQuestion::factory()->create([
        'tenant_id' => $this->tenant->id,
    ]);

    $response = $this->patchJson(
        "/api/v1/assessment/questions/{$question->id}",
        ['question' => 'Updated Question'],
        ['X-Tenant-ID' => (string) $this->tenant->id],
    );

    $response->assertSuccessful();
    $response->assertJsonPath('data.question', 'Updated Question');
});

it('cannot update question used in completed attempt', function (): void {
    Sanctum::actingAs($this->developer);

    $questionnaire = Questionnaire::factory()->create([
        'tenant_id' => $this->tenant->id,
    ]);

    $question = QuizQuestion::factory()->create([
        'tenant_id' => $this->tenant->id,
    ]);

    QuestionnaireQuestion::factory()->create([
        'questionnaire_id' => $questionnaire->id,
        'quiz_question_id' => $question->id,
    ]);

    $attempt = QuizAttempt::factory()->create([
        'tenant_id' => $this->tenant->id,
        'user_id' => $this->developer->id,
        'questionnaire_id' => $questionnaire->id,
        'status' => 'completed',
    ]);

    $response = $this->patchJson(
        "/api/v1/assessment/questions/{$question->id}",
        ['question' => 'Updated Question'],
        ['X-Tenant-ID' => (string) $this->tenant->id],
    );

    $response->assertStatus(422);
});
