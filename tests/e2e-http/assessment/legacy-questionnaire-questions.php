<?php

declare(strict_types=1);

use App\Modules\Assessment\Models\Questionnaire;
use App\Modules\Assessment\Models\QuestionnaireQuestion;
use App\Modules\Assessment\Models\QuizQuestion;

return [
    'endpoint' => 'GET /api/v1/assessment/questionnaires/{id}/questions',

    'setup' => function (array $ctx): array {
        $questionnaire = Questionnaire::factory()->for($ctx['tenant'])->create([
            'title' => 'E2E Legacy Questions Questionnaire',
        ]);
        $firstQuestion = QuizQuestion::factory()->for($ctx['tenant'])->create([
            'question' => 'E2E Legacy First Question',
        ]);
        $secondQuestion = QuizQuestion::factory()->for($ctx['tenant'])->create([
            'question' => 'E2E Legacy Second Question',
        ]);

        QuestionnaireQuestion::factory()->create([
            'questionnaire_id' => $questionnaire->id,
            'quiz_question_id' => $firstQuestion->id,
            'sort_order' => 1,
        ]);

        return [
            'questionnaireId' => $questionnaire->id,
            'firstQuestionId' => $firstQuestion->id,
            'secondQuestionId' => $secondQuestion->id,
        ];
    },

    'cases' => [
        [
            'name' => 'sem autenticação não lista questões legadas',
            'method' => 'GET',
            'path' => fn (array $ctx): string => '/api/v1/assessment/questionnaires/'.$ctx['fixtures']['questionnaireId'].'/questions',
            'expect' => ['status' => 401, 'json' => ['errors.0.code' => 'unauthenticated']],
        ],
        [
            'name' => 'admin lista questões legadas ordenadas',
            'as' => 'admin',
            'method' => 'GET',
            'path' => fn (array $ctx): string => '/api/v1/assessment/questionnaires/'.$ctx['fixtures']['questionnaireId'].'/questions',
            'expect' => [
                'status' => 200,
                'json' => [
                    'data.0.id' => fn (array $ctx): int => $ctx['fixtures']['firstQuestionId'],
                    'data.0.sort_order' => 1,
                ],
            ],
        ],
        [
            'name' => 'admin anexa questão legada do mesmo tenant',
            'as' => 'admin',
            'method' => 'POST',
            'path' => fn (array $ctx): string => '/api/v1/assessment/questionnaires/'.$ctx['fixtures']['questionnaireId'].'/questions',
            'body' => ['question_ids' => fn (array $ctx): array => [$ctx['fixtures']['secondQuestionId']]],
            'expect' => [
                'status' => 200,
                'json' => ['data.questions.1.id' => fn (array $ctx): int => $ctx['fixtures']['secondQuestionId']],
            ],
            'db' => fn (array $ctx): array => [
                'question attached in requested questionnaire' => [true, QuestionnaireQuestion::query()
                    ->where('questionnaire_id', $ctx['fixtures']['questionnaireId'])
                    ->where('quiz_question_id', $ctx['fixtures']['secondQuestionId'])
                    ->exists()],
            ],
        ],
        [
            'name' => 'admin remove questão legada não utilizada',
            'as' => 'admin',
            'method' => 'DELETE',
            'path' => fn (array $ctx): string => '/api/v1/assessment/questions/'.$ctx['fixtures']['secondQuestionId'],
            'expect' => ['status' => 200, 'json' => ['data' => null]],
            'db' => fn (array $ctx): array => [
                'question removed' => [false, QuizQuestion::query()->whereKey($ctx['fixtures']['secondQuestionId'])->exists()],
                'pivot removed by foreign key' => [false, QuestionnaireQuestion::query()
                    ->where('questionnaire_id', $ctx['fixtures']['questionnaireId'])
                    ->where('quiz_question_id', $ctx['fixtures']['secondQuestionId'])
                    ->exists()],
            ],
        ],
        [
            'name' => 'admin de outro tenant não alcança questionário legado',
            'as' => 'otherAdmin',
            'tenant' => 'primary',
            'method' => 'GET',
            'path' => fn (array $ctx): string => '/api/v1/assessment/questionnaires/'.$ctx['fixtures']['questionnaireId'].'/questions',
            'expect' => ['status' => 403, 'json' => ['errors.0.code' => 'access_denied']],
        ],
    ],

    'cleanup' => function (array $ctx): void {
        Questionnaire::query()->whereKey($ctx['fixtures']['questionnaireId'] ?? 0)->delete();
        QuizQuestion::query()->whereKey($ctx['fixtures']['firstQuestionId'] ?? 0)->delete();
        QuizQuestion::query()->whereKey($ctx['fixtures']['secondQuestionId'] ?? 0)->delete();
    },
];
