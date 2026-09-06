<?php

use App\Modules\Assessment\Models\Questionnaire;
use App\Modules\Assessment\Models\QuizQuestion;
use App\Modules\Core\Enums\UserType;
use App\Modules\Core\Models\User;
use App\Modules\Learning\Models\Category;
use App\Modules\Learning\Models\Course;

it('lets a tenant admin create basic assessment records without instructor ownership', function (): void {
    [$admin, $headers] = actingAsUserType(UserType::Admin);

    $questionnaire = $this->postJson('/api/v1/admin/questionnaires', [
        'title' => 'Avaliação Administrativa',
        'type' => 'standalone',
        'passing_score' => 70,
    ], $headers)
        ->assertCreated()
        ->assertJsonPath('data.instructor.id', null)
        ->json('data');

    $question = $this->postJson('/api/v1/admin/questions', [
        'question' => 'Qual é a resposta?',
        'type' => 'single_choice',
        'options' => [
            ['text' => 'A', 'correct' => true],
            ['text' => 'B', 'correct' => false],
        ],
        'correct_options' => [0],
    ], $headers)
        ->assertCreated()
        ->assertJsonPath('data.instructor.id', null)
        ->json('data');

    expect(Questionnaire::query()->findOrFail($questionnaire['id'])->instructor_id)->toBeNull()
        ->and(QuizQuestion::query()->findOrFail($question['id'])->instructor_id)->toBeNull()
        ->and($admin->isAdmin())->toBeTrue();
});

it('lists only the current tenant assessment records in Admin', function (): void {
    [$adminA, $headersA] = actingAsUserType(UserType::Admin);
    $tenantB = makeTenant();

    Questionnaire::factory()->for($adminA->tenant)->create(['title' => 'Questionário A']);
    Questionnaire::factory()->for($tenantB)->create(['title' => 'Questionário B']);
    QuizQuestion::factory()->for($adminA->tenant)->create(['question' => 'Questão A']);
    QuizQuestion::factory()->for($tenantB)->create(['question' => 'Questão B']);

    $this->getJson('/api/v1/admin/questionnaires', $headersA)
        ->assertOk()
        ->assertJsonFragment(['title' => 'Questionário A'])
        ->assertJsonMissing(['title' => 'Questionário B']);

    $this->getJson('/api/v1/admin/questions', $headersA)
        ->assertOk()
        ->assertJsonFragment(['question' => 'Questão A'])
        ->assertJsonMissing(['question' => 'Questão B']);
});

it('preserves existing instructor ownership when Admin updates assessment records', function (): void {
    [$admin, $headers] = actingAsUserType(UserType::Admin);
    $instructor = User::factory()->instructor()->forTenant($admin->tenant)->create();

    $questionnaire = Questionnaire::factory()->for($admin->tenant)->create([
        'instructor_id' => $instructor->id,
        'title' => 'Original',
    ]);
    $question = QuizQuestion::factory()->for($admin->tenant)->create([
        'instructor_id' => $instructor->id,
        'question' => 'Original question',
    ]);

    $this->patchJson('/api/v1/admin/questionnaires/'.$questionnaire->id, [
        'title' => 'Atualizado pelo Admin',
    ], $headers)->assertOk();

    $this->patchJson('/api/v1/admin/questions/'.$question->id, [
        'question' => 'Atualizada pelo Admin',
    ], $headers)->assertOk();

    expect($questionnaire->fresh()->instructor_id)->toBe($instructor->id)
        ->and($question->fresh()->instructor_id)->toBe($instructor->id);
});

it('rejects Admin assessment links and categories from another tenant', function (): void {
    [$admin, $headers] = actingAsUserType(UserType::Admin);
    $foreignTenant = makeTenant();
    $foreignCourse = Course::factory()->for($foreignTenant)->create();
    $foreignCategory = Category::factory()->for($foreignTenant)->create();

    assertApiErrorEnvelope(
        $this->postJson('/api/v1/admin/questionnaires', [
            'title' => 'Link cross-tenant',
            'type' => 'course',
            'quizable_type' => 'course',
            'quizable_id' => $foreignCourse->id,
        ], $headers),
        422,
        'validation_error',
    );

    assertApiErrorEnvelope(
        $this->postJson('/api/v1/admin/questions', [
            'question' => 'Categoria cross-tenant',
            'type' => 'single_choice',
            'options' => [
                ['text' => 'A', 'correct' => true],
                ['text' => 'B', 'correct' => false],
            ],
            'correct_options' => [0],
            'category_ids' => [$foreignCategory->id],
        ], $headers),
        422,
        'validation_error',
    );

    expect($admin->tenant_id)->not->toBe($foreignTenant->id);
});

it('hides cross-tenant Admin assessment records', function (): void {
    [$adminA, $headersA] = actingAsUserType(UserType::Admin);
    $foreignQuestionnaire = Questionnaire::factory()->for(makeTenant())->create();

    assertApiErrorEnvelope(
        $this->getJson('/api/v1/admin/questionnaires/'.$foreignQuestionnaire->id, $headersA),
        404,
        'not_found',
    );
});

it('denies instructor access to the Admin assessment area', function (): void {
    [$instructor, $headers] = actingAsUserType(UserType::Instructor);

    assertApiErrorEnvelope(
        $this->getJson('/api/v1/admin/questionnaires', $headers),
        403,
        'area_forbidden',
    );

    expect($instructor->isInstructor())->toBeTrue();
});

it('denies developer access to the Admin assessment area', function (): void {
    [, $headers] = actingAsUserType(UserType::Developer);

    assertApiErrorEnvelope(
        $this->getJson('/api/v1/admin/questionnaires', $headers),
        403,
        'area_forbidden',
    );
});
