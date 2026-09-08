<?php

use App\Modules\Assessment\Models\Questionnaire;
use App\Modules\Assessment\Models\QuestionnaireQuestion;
use App\Modules\Assessment\Models\QuizAttempt;
use App\Modules\Assessment\Models\QuizQuestion;
use App\Modules\Core\Enums\UserType;

beforeEach(function (): void {
    $this->tenant = makeTenant();
    [$this->student, $this->headers] = actingAsUserType(UserType::Student, $this->tenant);

    $questionnaire = Questionnaire::factory()->create([
        'tenant_id' => $this->tenant->id,
    ]);
    $question = QuizQuestion::factory()->create([
        'tenant_id' => $this->tenant->id,
    ]);

    QuestionnaireQuestion::query()->create([
        'questionnaire_id' => $questionnaire->id,
        'quiz_question_id' => $question->id,
        'sort_order' => 1,
    ]);

    $this->questionnaire = $questionnaire;
});

it('blocks Student from every legacy Assessment attempt operation', function (string $method, string $path): void {
    $response = match ($method) {
        'GET' => $this->getJson($path, $this->headers),
        'PATCH' => $this->patchJson($path, [
            'question_id' => 1,
            'selected_options' => [0],
        ], $this->headers),
        'POST' => $this->postJson($path, [], $this->headers),
    };

    assertApiErrorEnvelope($response, 403, 'access_denied');
    expect(QuizAttempt::query()->count())->toBe(0);
})->with(function (): array {
    return [
        'start' => ['POST', '/api/v1/assessment/attempts/questionnaires/1'],
        'show' => ['GET', '/api/v1/assessment/attempts/1'],
        'answer' => ['PATCH', '/api/v1/assessment/attempts/1'],
        'finish' => ['POST', '/api/v1/assessment/attempts/1/finish'],
        'certificates' => ['GET', '/api/v1/assessment/certificates'],
        'certificate' => ['GET', '/api/v1/assessment/certificates/1'],
    ];
});
