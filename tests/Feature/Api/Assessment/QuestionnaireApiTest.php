<?php

use App\Modules\Assessment\Models\Questionnaire;
use App\Modules\Assessment\Models\QuestionnaireQuestion;
use App\Modules\Assessment\Models\QuizAttempt;
use App\Modules\Assessment\Models\QuizQuestion;
use App\Modules\Core\Enums\UserType;
use App\Modules\Core\Models\Tenant;
use App\Modules\Core\Models\User;
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

it('lists a questionnaire questions ordered by sort order', function (): void {
    Sanctum::actingAs($this->developer);

    $questionnaire = Questionnaire::factory()->create([
        'tenant_id' => $this->tenant->id,
    ]);
    $firstQuestion = QuizQuestion::factory()->create([
        'tenant_id' => $this->tenant->id,
        'question' => 'First question',
    ]);
    $secondQuestion = QuizQuestion::factory()->create([
        'tenant_id' => $this->tenant->id,
        'question' => 'Second question',
    ]);

    QuestionnaireQuestion::factory()->create([
        'questionnaire_id' => $questionnaire->id,
        'quiz_question_id' => $secondQuestion->id,
        'sort_order' => 1,
    ]);
    QuestionnaireQuestion::factory()->create([
        'questionnaire_id' => $questionnaire->id,
        'quiz_question_id' => $firstQuestion->id,
        'sort_order' => 2,
    ]);

    $response = $this->getJson(
        "/api/v1/assessment/questionnaires/{$questionnaire->id}/questions",
        ['X-Tenant-ID' => (string) $this->tenant->id],
    );

    $response->assertSuccessful()
        ->assertJsonPath('data.0.id', $secondQuestion->id)
        ->assertJsonPath('data.0.sort_order', 1)
        ->assertJsonPath('data.0.question.question', 'Second question')
        ->assertJsonPath('data.1.id', $firstQuestion->id);
});

it('does not list questions from another tenant', function (): void {
    Sanctum::actingAs($this->developer);

    $foreignQuestionnaire = Questionnaire::factory()->for(makeTenant())->create();

    assertApiErrorEnvelope(
        $this->getJson(
            "/api/v1/assessment/questionnaires/{$foreignQuestionnaire->id}/questions",
            ['X-Tenant-ID' => (string) $this->tenant->id],
        ),
        404,
        'not_found',
    );
});

it('attaches tenant questions to a questionnaire in request order', function (): void {
    Sanctum::actingAs($this->developer);

    $questionnaire = Questionnaire::factory()->create([
        'tenant_id' => $this->tenant->id,
    ]);
    $firstQuestion = QuizQuestion::factory()->create(['tenant_id' => $this->tenant->id]);
    $secondQuestion = QuizQuestion::factory()->create(['tenant_id' => $this->tenant->id]);

    $response = $this->postJson(
        "/api/v1/assessment/questionnaires/{$questionnaire->id}/questions",
        ['question_ids' => [$secondQuestion->id, $firstQuestion->id]],
        ['X-Tenant-ID' => (string) $this->tenant->id],
    );

    $response->assertSuccessful()
        ->assertJsonPath('data.questions.0.id', $secondQuestion->id)
        ->assertJsonPath('data.questions.0.sort_order', 1)
        ->assertJsonPath('data.questions.1.id', $firstQuestion->id)
        ->assertJsonPath('data.questions.1.sort_order', 2);

    expect(QuestionnaireQuestion::query()
        ->where('questionnaire_id', $questionnaire->id)
        ->orderBy('sort_order')
        ->pluck('quiz_question_id')
        ->all())->toBe([$secondQuestion->id, $firstQuestion->id]);
});

it('does not attach a question from another tenant', function (): void {
    Sanctum::actingAs($this->developer);

    $questionnaire = Questionnaire::factory()->create([
        'tenant_id' => $this->tenant->id,
    ]);
    $foreignQuestion = QuizQuestion::factory()->for(makeTenant())->create();

    assertApiErrorEnvelope(
        $this->postJson(
            "/api/v1/assessment/questionnaires/{$questionnaire->id}/questions",
            ['question_ids' => [$foreignQuestion->id]],
            ['X-Tenant-ID' => (string) $this->tenant->id],
        ),
        404,
        'not_found',
    );
});

it('rejects attaching the same question twice', function (): void {
    Sanctum::actingAs($this->developer);

    $questionnaire = Questionnaire::factory()->create([
        'tenant_id' => $this->tenant->id,
    ]);
    $question = QuizQuestion::factory()->create(['tenant_id' => $this->tenant->id]);

    $this->postJson(
        "/api/v1/assessment/questionnaires/{$questionnaire->id}/questions",
        ['question_ids' => [$question->id]],
        ['X-Tenant-ID' => (string) $this->tenant->id],
    )->assertSuccessful();

    assertApiErrorEnvelope(
        $this->postJson(
            "/api/v1/assessment/questionnaires/{$questionnaire->id}/questions",
            ['question_ids' => [$question->id]],
            ['X-Tenant-ID' => (string) $this->tenant->id],
        ),
        422,
        'validation_error',
    );
});

it('rejects attaching questions after the questionnaire has an attempt', function (): void {
    Sanctum::actingAs($this->developer);

    $questionnaire = Questionnaire::factory()->create([
        'tenant_id' => $this->tenant->id,
    ]);
    $question = QuizQuestion::factory()->create(['tenant_id' => $this->tenant->id]);
    QuizAttempt::factory()->create([
        'tenant_id' => $this->tenant->id,
        'user_id' => $this->developer->id,
        'questionnaire_id' => $questionnaire->id,
        'status' => 'in_progress',
    ]);

    assertApiErrorEnvelope(
        $this->postJson(
            "/api/v1/assessment/questionnaires/{$questionnaire->id}/questions",
            ['question_ids' => [$question->id]],
            ['X-Tenant-ID' => (string) $this->tenant->id],
        ),
        422,
        'validation_error',
    );

    expect(QuestionnaireQuestion::query()
        ->where('questionnaire_id', $questionnaire->id)
        ->exists())->toBeFalse();
});
