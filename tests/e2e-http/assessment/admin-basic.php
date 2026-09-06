<?php

declare(strict_types=1);

use App\Modules\Assessment\Models\Questionnaire;
use App\Modules\Assessment\Models\QuizQuestion;

return [
    'endpoint' => 'POST /api/v1/admin/questionnaires',

    'setup' => function (array $ctx): array {
        $foreignQuestionnaire = Questionnaire::factory()->for($ctx['otherTenant'])->create([
            'title' => 'E2E Foreign Questionnaire',
        ]);

        return ['foreignQuestionnaireId' => $foreignQuestionnaire->id];
    },

    'cases' => [
        [
            'name' => 'admin cria questionnaire sem instructor ownership',
            'as' => 'admin',
            'path' => '/api/v1/admin/questionnaires',
            'body' => ['title' => 'E2E Admin Questionnaire', 'type' => 'standalone'],
            'expect' => ['status' => 201, 'json' => ['data.instructor.id' => null]],
            'capture' => fn (array $ctx): array => ['questionnaireId' => $ctx['response']->json('data.id')],
            'db' => function (array $ctx): array {
                $questionnaire = Questionnaire::query()->find($ctx['fixtures']['questionnaireId']);

                return [
                    'questionnaire belongs to primary tenant' => [$ctx['tenant']->id, $questionnaire?->tenant_id],
                    'admin creation has no instructor' => [null, $questionnaire?->instructor_id],
                ];
            },
        ],
        [
            'name' => 'admin cria question sem instructor ownership',
            'as' => 'admin',
            'path' => '/api/v1/admin/questions',
            'body' => [
                'question' => 'E2E Admin Question',
                'type' => 'single_choice',
                'options' => [
                    ['text' => 'A', 'correct' => true],
                    ['text' => 'B', 'correct' => false],
                ],
                'correct_options' => [0],
            ],
            'expect' => ['status' => 201, 'json' => ['data.instructor.id' => null]],
            'capture' => fn (array $ctx): array => ['questionId' => $ctx['response']->json('data.id')],
            'db' => function (array $ctx): array {
                $question = QuizQuestion::query()->find($ctx['fixtures']['questionId']);

                return [
                    'question belongs to primary tenant' => [$ctx['tenant']->id, $question?->tenant_id],
                    'admin creation has no instructor' => [null, $question?->instructor_id],
                ];
            },
        ],
        [
            'name' => 'admin atualiza sem assumir ownership',
            'as' => 'admin',
            'method' => 'PATCH',
            'path' => fn (array $ctx): string => '/api/v1/admin/questionnaires/'.$ctx['fixtures']['questionnaireId'],
            'body' => ['title' => 'E2E Admin Questionnaire Updated'],
            'expect' => ['status' => 200, 'json' => ['data.title' => 'E2E Admin Questionnaire Updated', 'data.instructor.id' => null]],
            'db' => fn (array $ctx): array => [
                'ownership remains null' => [null, Questionnaire::query()->find($ctx['fixtures']['questionnaireId'])?->instructor_id],
            ],
        ],
        [
            'name' => 'admin vê apenas assessment do próprio tenant',
            'as' => 'admin',
            'method' => 'GET',
            'path' => '/api/v1/admin/questionnaires',
            'expect' => ['status' => 200],
            'db' => fn (array $ctx): array => [
                'foreign questionnaire is not in primary tenant' => [false, Questionnaire::query()
                    ->where('tenant_id', $ctx['tenant']->id)
                    ->where('title', 'E2E Foreign Questionnaire')
                    ->exists()],
            ],
        ],
        [
            'name' => 'admin recebe 404 defensivo para foreign questionnaire',
            'as' => 'admin',
            'method' => 'GET',
            'path' => fn (array $ctx): string => '/api/v1/admin/questionnaires/'.$ctx['fixtures']['foreignQuestionnaireId'],
            'expect' => ['status' => 404, 'json' => ['errors.0.code' => 'not_found']],
        ],
        [
            'name' => 'instructor é barrado da área Admin',
            'as' => 'instructor',
            'path' => '/api/v1/admin/questionnaires',
            'expect' => ['status' => 403, 'json' => ['errors.0.code' => 'area_forbidden']],
        ],
        [
            'name' => 'developer é barrado da área Admin',
            'as' => 'developer',
            'tenant' => null,
            'path' => '/api/v1/admin/questionnaires',
            'expect' => ['status' => 403, 'json' => ['errors.0.code' => 'area_forbidden']],
        ],
    ],

    'cleanup' => function (array $ctx): void {
        Questionnaire::query()
            ->whereIn('id', array_filter([
                $ctx['fixtures']['foreignQuestionnaireId'] ?? null,
                $ctx['fixtures']['questionnaireId'] ?? null,
            ]))
            ->delete();
        QuizQuestion::query()->whereKey($ctx['fixtures']['questionId'] ?? 0)->delete();
    },
];
