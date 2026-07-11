<?php

use App\Modules\Assessment\Models\Questionnaire;
use App\Modules\Assessment\Models\QuestionnaireQuestion;
use App\Modules\Assessment\Models\QuizAttempt;
use App\Modules\Assessment\Models\QuizAttemptAnswer;
use App\Modules\Assessment\Models\QuizQuestion;
use App\Modules\Core\Enums\UserType;

beforeEach(function (): void {
    $this->tenant = makeTenant();
    [$this->student, $this->headers] = actingAsUserType(UserType::Student, $this->tenant);

    $this->questionnaire = Questionnaire::factory()->create([
        'tenant_id' => $this->tenant->id,
        'passing_score' => 70,
    ]);
});

function linkQuestionToQuestionnaire(Questionnaire $questionnaire, QuizQuestion $question, int $sortOrder = 1): void
{
    QuestionnaireQuestion::query()->create([
        'questionnaire_id' => $questionnaire->id,
        'quiz_question_id' => $question->id,
        'sort_order' => $sortOrder,
    ]);
}

it('starts an attempt freezing questions server-side', function (): void {
    $question = QuizQuestion::factory()->create([
        'tenant_id' => $this->tenant->id,
        'correct_options' => [1],
        'points' => 2,
    ]);
    linkQuestionToQuestionnaire($this->questionnaire, $question);

    $response = $this->postJson(
        "/api/v1/assessment/attempts/questionnaires/{$this->questionnaire->id}",
        [],
        $this->headers,
    );

    $response->assertSuccessful();
    $response->assertJsonStructure([
        'data' => ['id', 'questionnaire_id', 'status', 'started_at', 'questions'],
    ]);
    $response->assertJsonPath('data.questions.0.id', $question->id);
    $response->assertJsonMissingPath('data.questions.0.correct_options');
    $response->assertJsonMissingPath('data.questions.0.explanation');

    $attempt = QuizAttempt::query()
        ->where('questionnaire_id', $this->questionnaire->id)
        ->firstOrFail();

    expect($attempt->questions_snapshot)->toHaveCount(1)
        ->and($attempt->questions_snapshot[0]['id'])->toBe($question->id)
        ->and($attempt->questions_snapshot[0]['correct_options'])->toBe([1])
        ->and($attempt->questions_snapshot[0]['points'])->toBe(2);
});

it('refuses to start an attempt when the questionnaire has no active questions', function (): void {
    $inactiveQuestion = QuizQuestion::factory()->inactive()->create([
        'tenant_id' => $this->tenant->id,
    ]);
    linkQuestionToQuestionnaire($this->questionnaire, $inactiveQuestion);

    $response = $this->postJson(
        "/api/v1/assessment/attempts/questionnaires/{$this->questionnaire->id}",
        [],
        $this->headers,
    );

    $response->assertStatus(422);
});

it('denies instructor starting an attempt', function (): void {
    [$instructor, $headers] = actingAsUserType(UserType::Instructor, $this->tenant);

    $response = $this->postJson(
        "/api/v1/assessment/attempts/questionnaires/{$this->questionnaire->id}",
        [],
        $headers,
    );

    assertApiErrorEnvelope($response, 403, 'access_denied');
});

it('shows an attempt without exposing the answer key', function (): void {
    $attempt = QuizAttempt::factory()->create([
        'tenant_id' => $this->tenant->id,
        'user_id' => $this->student->id,
        'questionnaire_id' => $this->questionnaire->id,
    ]);

    $response = $this->getJson(
        "/api/v1/assessment/attempts/{$attempt->id}",
        $this->headers,
    );

    $response->assertSuccessful();
    $response->assertJsonPath('data.id', $attempt->id);
    $response->assertJsonMissingPath('data.questions.0.correct_options');
});

it('submits an answer scored from the server snapshot', function (): void {
    $attempt = QuizAttempt::factory()->create([
        'tenant_id' => $this->tenant->id,
        'user_id' => $this->student->id,
        'questionnaire_id' => $this->questionnaire->id,
    ]);

    $response = $this->patchJson(
        "/api/v1/assessment/attempts/{$attempt->id}",
        [
            'question_id' => 1,
            'selected_options' => [0],
        ],
        $this->headers,
    );

    $response->assertSuccessful();
    $response->assertJsonPath('data.is_correct', true);
    $response->assertJsonPath('data.points_earned', 1);
    $response->assertJsonMissingPath('data.question_snapshot.correct_options');
});

it('ignores a forged answer key sent by the client', function (): void {
    $attempt = QuizAttempt::factory()->create([
        'tenant_id' => $this->tenant->id,
        'user_id' => $this->student->id,
        'questionnaire_id' => $this->questionnaire->id,
    ]);

    $response = $this->patchJson(
        "/api/v1/assessment/attempts/{$attempt->id}",
        [
            'question_id' => 1,
            'selected_options' => [1],
            'question_snapshot' => [
                'correct_options' => [1],
                'points' => 999,
            ],
        ],
        $this->headers,
    );

    $response->assertSuccessful();
    $response->assertJsonPath('data.is_correct', false);
    $response->assertJsonPath('data.points_earned', 0);

    $answer = QuizAttemptAnswer::query()
        ->where('quiz_attempt_id', $attempt->id)
        ->firstOrFail();

    expect($answer->is_correct)->toBeFalse()
        ->and($answer->points_earned)->toBe(0)
        ->and($answer->question_snapshot['correct_options'])->toBe([0]);
});

it('rejects the legacy payload without question_id', function (): void {
    $attempt = QuizAttempt::factory()->create([
        'tenant_id' => $this->tenant->id,
        'user_id' => $this->student->id,
        'questionnaire_id' => $this->questionnaire->id,
    ]);

    $response = $this->patchJson(
        "/api/v1/assessment/attempts/{$attempt->id}",
        [
            'question_snapshot' => [
                'question' => 'Forged?',
                'type' => 'single_choice',
                'options' => [['text' => 'A'], ['text' => 'B']],
                'correct_options' => [0],
                'points' => 999,
            ],
            'selected_options' => [0],
        ],
        $this->headers,
    );

    assertApiErrorEnvelope($response, 422, 'validation_error');
});

it('rejects an answer for a question outside the frozen snapshot', function (): void {
    $attempt = QuizAttempt::factory()->create([
        'tenant_id' => $this->tenant->id,
        'user_id' => $this->student->id,
        'questionnaire_id' => $this->questionnaire->id,
    ]);

    $response = $this->patchJson(
        "/api/v1/assessment/attempts/{$attempt->id}",
        [
            'question_id' => 999,
            'selected_options' => [0],
        ],
        $this->headers,
    );

    $response->assertStatus(422);
    expect(QuizAttemptAnswer::query()->where('quiz_attempt_id', $attempt->id)->exists())->toBeFalse();
});

it('rejects answering the same question twice', function (): void {
    $attempt = QuizAttempt::factory()->create([
        'tenant_id' => $this->tenant->id,
        'user_id' => $this->student->id,
        'questionnaire_id' => $this->questionnaire->id,
    ]);

    $payload = ['question_id' => 1, 'selected_options' => [0]];

    $this->patchJson("/api/v1/assessment/attempts/{$attempt->id}", $payload, $this->headers)
        ->assertSuccessful();

    $response = $this->patchJson("/api/v1/assessment/attempts/{$attempt->id}", $payload, $this->headers);

    $response->assertStatus(422);
    expect(QuizAttemptAnswer::query()->where('quiz_attempt_id', $attempt->id)->count())->toBe(1);
});

it('denies answering an attempt of another user', function (): void {
    $otherTenant = makeTenant();
    [$otherStudent] = actingAsUserType(UserType::Student, $otherTenant);

    $attempt = QuizAttempt::factory()->create([
        'tenant_id' => $otherTenant->id,
        'user_id' => $otherStudent->id,
    ]);

    $response = $this->patchJson(
        "/api/v1/assessment/attempts/{$attempt->id}",
        ['question_id' => 1, 'selected_options' => [0]],
        $this->headers,
    );

    assertTenantIsolation($response);
});

it('requires authentication to answer', function (): void {
    $attempt = QuizAttempt::factory()->create([
        'tenant_id' => $this->tenant->id,
        'user_id' => $this->student->id,
        'questionnaire_id' => $this->questionnaire->id,
    ]);

    $response = $this->patchJson(
        "/api/v1/assessment/attempts/{$attempt->id}",
        ['question_id' => 1, 'selected_options' => [0]],
        tenantHeaders($this->tenant),
    );

    assertApiErrorEnvelope($response, 401, 'unauthenticated');
});

it('finishes an attempt computing the score from the frozen snapshot', function (): void {
    $attempt = QuizAttempt::factory()->create([
        'tenant_id' => $this->tenant->id,
        'user_id' => $this->student->id,
        'questionnaire_id' => $this->questionnaire->id,
        'questionnaire_snapshot' => [
            'title' => $this->questionnaire->title,
            'passing_score' => 70,
        ],
        'questions_snapshot' => [
            ['id' => 1, 'question' => 'Q1?', 'type' => 'single_choice', 'options' => [['text' => 'A'], ['text' => 'B']], 'correct_options' => [0], 'points' => 1, 'sort_order' => 1],
            ['id' => 2, 'question' => 'Q2?', 'type' => 'single_choice', 'options' => [['text' => 'A'], ['text' => 'B']], 'correct_options' => [1], 'points' => 1, 'sort_order' => 2],
        ],
    ]);

    $this->patchJson(
        "/api/v1/assessment/attempts/{$attempt->id}",
        ['question_id' => 1, 'selected_options' => [0]],
        $this->headers,
    )->assertSuccessful();

    $response = $this->postJson(
        "/api/v1/assessment/attempts/{$attempt->id}/finish",
        [],
        $this->headers,
    );

    $response->assertSuccessful();
    $response->assertJsonPath('data.status', 'completed');
    $response->assertJsonPath('data.score', 50);
    $response->assertJsonPath('data.passed', false);
});

it('freezes questions and computes score entirely server-side across the full flow', function (): void {
    $questionA = QuizQuestion::factory()->create([
        'tenant_id' => $this->tenant->id,
        'correct_options' => [1],
        'points' => 3,
    ]);
    $questionB = QuizQuestion::factory()->create([
        'tenant_id' => $this->tenant->id,
        'correct_options' => [0],
        'points' => 1,
    ]);
    linkQuestionToQuestionnaire($this->questionnaire, $questionA, 1);
    linkQuestionToQuestionnaire($this->questionnaire, $questionB, 2);

    $startResponse = $this->postJson(
        "/api/v1/assessment/attempts/questionnaires/{$this->questionnaire->id}",
        [],
        $this->headers,
    );

    $startResponse->assertSuccessful();
    $attemptId = $startResponse->json('data.id');

    $this->patchJson(
        "/api/v1/assessment/attempts/{$attemptId}",
        ['question_id' => $questionA->id, 'selected_options' => [1]],
        $this->headers,
    )->assertSuccessful();

    $this->patchJson(
        "/api/v1/assessment/attempts/{$attemptId}",
        ['question_id' => $questionB->id, 'selected_options' => [2]],
        $this->headers,
    )->assertSuccessful();

    $response = $this->postJson(
        "/api/v1/assessment/attempts/{$attemptId}/finish",
        [],
        $this->headers,
    );

    $response->assertSuccessful();
    $response->assertJsonPath('data.score', 75);
    $response->assertJsonPath('data.passed', true);
});
