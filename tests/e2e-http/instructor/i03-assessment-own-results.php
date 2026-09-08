<?php

declare(strict_types=1);

use App\Modules\Assessment\Models\Questionnaire;
use App\Modules\Assessment\Models\QuizQuestion;
use App\Modules\Core\Models\User;
use App\Modules\Learning\Models\Course;
use App\Modules\Learning\Models\Enrollment;

return [
    'endpoint' => 'POST /api/v1/instructor/assessment/questionnaires',

    'setup' => function (array $ctx): array {
        $instructor = $ctx['users']['instructor'];
        $instructorB = User::factory()->instructor()->forTenant($ctx['tenant'])->create(['name' => 'E2E I03 Instructor B']);
        $instructorB->assignRole('instructor');
        $instructorBToken = $instructorB->createToken('e2e-i03-instructor-b')->plainTextToken;

        $courseB = Course::factory()->for($ctx['tenant'])->create([
            'instructor_id' => $instructorB->id,
            'title' => 'E2E I03 Course B',
        ]);
        $courseA = Course::factory()->for($ctx['tenant'])->create([
            'instructor_id' => $instructor->id,
            'title' => 'E2E I03 Course A',
        ]);
        $foreignCourse = Course::factory()->for($ctx['otherTenant'])->create([
            'instructor_id' => $instructor->id,
            'title' => 'E2E I03 Foreign Course',
        ]);
        $adminQuestionnaire = Questionnaire::factory()->course()->for($ctx['tenant'])->create([
            'instructor_id' => null,
            'quizable_type' => 'course',
            'quizable_id' => $courseB->id,
            'title' => 'E2E I03 Admin Owned',
        ]);
        Enrollment::factory()->active()->for($ctx['tenant'])->for($ctx['users']['student'], 'user')->for($courseA, 'course')->create();
        Enrollment::factory()->active()->for($ctx['tenant'])->for($ctx['users']['student'], 'user')->for($courseB, 'course')->create();

        return [
            'instructorB' => $instructorB,
            'instructorBToken' => $instructorBToken,
            'courseAId' => $courseA->id,
            'courseBId' => $courseB->id,
            'foreignCourseId' => $foreignCourse->id,
            'adminQuestionnaireId' => $adminQuestionnaire->id,
        ];
    },

    'cases' => [
        [
            'name' => 'Instructor A cria Questionnaire próprio em Course próprio',
            'as' => 'instructor',
            'body' => [
                'title' => 'E2E I03 Questionnaire A',
                'type' => 'course',
                'quizable_type' => 'course',
                'quizable_id' => fn (array $ctx): int => $ctx['fixtures']['courseAId'],
                'passing_score' => 70,
            ],
            'expect' => ['status' => 201, 'json' => ['data.instructor_id' => fn (array $ctx): int => $ctx['users']['instructor']->id]],
            'capture' => fn (array $ctx): array => ['questionnaireAId' => (int) $ctx['response']->json('data.id')],
        ],
        [
            'name' => 'Instructor A cria duas Questions próprias',
            'as' => 'instructor',
            'method' => 'POST',
            'path' => '/api/v1/instructor/assessment/questions',
            'body' => [
                'question' => 'E2E I03 Question A1',
                'type' => 'single_choice',
                'options' => [['text' => 'Correta'], ['text' => 'Errada']],
                'correct_options' => [0],
                'explanation' => 'Feedback permitido.',
                'points' => 2,
            ],
            'expect' => ['status' => 201],
            'capture' => fn (array $ctx): array => ['questionA1Id' => (int) $ctx['response']->json('data.id')],
        ],
        [
            'name' => 'Instructor A cria segunda Question reutilizável',
            'as' => 'instructor',
            'method' => 'POST',
            'path' => '/api/v1/instructor/assessment/questions',
            'body' => [
                'question' => 'E2E I03 Question A2',
                'type' => 'true_false',
                'options' => [['text' => 'Verdadeiro'], ['text' => 'Falso']],
                'correct_options' => [0],
            ],
            'expect' => ['status' => 201],
            'capture' => fn (array $ctx): array => ['questionA2Id' => (int) $ctx['response']->json('data.id')],
        ],
        [
            'name' => 'Instructor A anexa e reordena Questions próprias',
            'as' => 'instructor',
            'method' => 'POST',
            'path' => fn (array $ctx): string => '/api/v1/instructor/assessment/questionnaires/'.$ctx['fixtures']['questionnaireAId'].'/questions',
            'body' => [
                'question_ids' => fn (array $ctx): array => [$ctx['fixtures']['questionA1Id'], $ctx['fixtures']['questionA2Id']],
            ],
            'expect' => ['status' => 200, 'json' => ['data.questions.0.id' => fn (array $ctx): int => $ctx['fixtures']['questionA1Id']]],
        ],
        [
            'name' => 'Instructor A reordena com conjunto fechado',
            'as' => 'instructor',
            'method' => 'PATCH',
            'path' => fn (array $ctx): string => '/api/v1/instructor/assessment/questionnaires/'.$ctx['fixtures']['questionnaireAId'].'/questions/reorder',
            'body' => ['question_ids' => fn (array $ctx): array => [$ctx['fixtures']['questionA2Id'], $ctx['fixtures']['questionA1Id']]],
            'expect' => ['status' => 200, 'json' => ['data.questions.0.id' => fn (array $ctx): int => $ctx['fixtures']['questionA2Id']]],
        ],
        [
            'name' => 'Instructor B não alcança Questionnaire A',
            'as' => 'instructor',
            'headers' => fn (array $ctx): array => ['Authorization' => 'Bearer '.$ctx['fixtures']['instructorBToken']],
            'method' => 'GET',
            'path' => fn (array $ctx): string => '/api/v1/instructor/assessment/questionnaires/'.$ctx['fixtures']['questionnaireAId'],
            'expect' => ['status' => 404, 'json' => ['errors.0.code' => 'not_found']],
        ],
        [
            'name' => 'Admin-owned null permanece invisível ao Instructor',
            'as' => 'instructor',
            'method' => 'GET',
            'path' => '/api/v1/instructor/assessment/questionnaires',
            'expect' => ['status' => 200],
            'db' => function (array $ctx): array {
                $ids = array_column($ctx['response']->json('data') ?? [], 'id');

                return [
                    'questionnaire próprio listado' => [true, in_array($ctx['fixtures']['questionnaireAId'], $ids, true)],
                    'admin-owned não listado' => [false, in_array($ctx['fixtures']['adminQuestionnaireId'], $ids, true)],
                ];
            },
        ],
        [
            'name' => 'Student inicia attempt no Assessment A',
            'as' => 'student',
            'method' => 'POST',
            'path' => fn (array $ctx): string => '/api/v1/assessment/attempts/questionnaires/'.$ctx['fixtures']['questionnaireAId'],
            'expect' => ['status' => 201],
            'capture' => fn (array $ctx): array => ['attemptId' => (int) $ctx['response']->json('data.id')],
        ],
        [
            'name' => 'Student responde e scoring permanece server-side',
            'as' => 'student',
            'method' => 'PATCH',
            'path' => fn (array $ctx): string => '/api/v1/assessment/attempts/'.$ctx['fixtures']['attemptId'],
            'body' => ['question_id' => fn (array $ctx): int => $ctx['fixtures']['questionA1Id'], 'selected_options' => [0], 'points_earned' => 999],
            'expect' => ['status' => 201, 'json' => ['data.is_correct' => true, 'data.points_earned' => 2]],
        ],
        [
            'name' => 'Student finaliza attempt e congela histórico',
            'as' => 'student',
            'method' => 'POST',
            'path' => fn (array $ctx): string => '/api/v1/assessment/attempts/'.$ctx['fixtures']['attemptId'].'/finish',
            'expect' => ['status' => 200],
        ],
        [
            'name' => 'Instructor A vê result e answers sem gabarito bruto/PII',
            'as' => 'instructor',
            'method' => 'GET',
            'path' => '/api/v1/instructor/assessment/results',
            'expect' => ['status' => 200, 'json' => ['data.0.attempt_id' => fn (array $ctx): int => $ctx['fixtures']['attemptId']]],
            'db' => function (array $ctx): array {
                $response = $ctx['response']->json();

                return [
                    'gabarito ausente' => [null, data_get($response, 'data.0.answers.0.correct_options')],
                    'email ausente' => [null, data_get($response, 'data.0.student.email')],
                    'attempt persistido' => [true, QuizQuestion::query()->whereKey($ctx['fixtures']['questionA1Id'])->exists()],
                ];
            },
        ],
        [
            'name' => 'Instructor B não vê result do Course A',
            'as' => 'instructor',
            'headers' => fn (array $ctx): array => ['Authorization' => 'Bearer '.$ctx['fixtures']['instructorBToken']],
            'method' => 'GET',
            'path' => fn (array $ctx): string => '/api/v1/instructor/assessment/results/'.$ctx['fixtures']['attemptId'],
            'expect' => ['status' => 404, 'json' => ['errors.0.code' => 'not_found']],
        ],
        [
            'name' => 'Questionnaire com attempt rejeita update/delete/reorder',
            'as' => 'instructor',
            'method' => 'PATCH',
            'path' => fn (array $ctx): string => '/api/v1/instructor/assessment/questionnaires/'.$ctx['fixtures']['questionnaireAId'],
            'body' => ['title' => 'não pode mudar'],
            'expect' => ['status' => 422, 'json' => ['errors.0.code' => 'validation_error']],
        ],
        [
            'name' => 'Questionnaire histórico rejeita delete',
            'as' => 'instructor',
            'method' => 'DELETE',
            'path' => fn (array $ctx): string => '/api/v1/instructor/assessment/questionnaires/'.$ctx['fixtures']['questionnaireAId'],
            'expect' => ['status' => 422, 'json' => ['errors.0.code' => 'validation_error']],
        ],
        [
            'name' => 'Questionnaire histórico rejeita attach',
            'as' => 'instructor',
            'method' => 'POST',
            'path' => fn (array $ctx): string => '/api/v1/instructor/assessment/questionnaires/'.$ctx['fixtures']['questionnaireAId'].'/questions',
            'body' => ['question_ids' => fn (array $ctx): array => [$ctx['fixtures']['questionA2Id']]],
            'expect' => ['status' => 422, 'json' => ['errors.0.code' => 'validation_error']],
        ],
        [
            'name' => 'Questionnaire histórico rejeita detach',
            'as' => 'instructor',
            'method' => 'DELETE',
            'path' => fn (array $ctx): string => '/api/v1/instructor/assessment/questionnaires/'.$ctx['fixtures']['questionnaireAId'].'/questions/'.$ctx['fixtures']['questionA2Id'],
            'expect' => ['status' => 422, 'json' => ['errors.0.code' => 'validation_error']],
        ],
        [
            'name' => 'Questionnaire histórico rejeita reorder',
            'as' => 'instructor',
            'method' => 'PATCH',
            'path' => fn (array $ctx): string => '/api/v1/instructor/assessment/questionnaires/'.$ctx['fixtures']['questionnaireAId'].'/questions/reorder',
            'body' => ['question_ids' => fn (array $ctx): array => [$ctx['fixtures']['questionA2Id'], $ctx['fixtures']['questionA1Id']]],
            'expect' => ['status' => 422, 'json' => ['errors.0.code' => 'validation_error']],
        ],
        [
            'name' => 'Question usada rejeita update/delete',
            'as' => 'instructor',
            'method' => 'PATCH',
            'path' => fn (array $ctx): string => '/api/v1/instructor/assessment/questions/'.$ctx['fixtures']['questionA1Id'],
            'body' => ['question' => 'não pode mudar'],
            'expect' => ['status' => 422, 'json' => ['errors.0.code' => 'validation_error']],
        ],
        [
            'name' => 'Question histórica rejeita delete',
            'as' => 'instructor',
            'method' => 'DELETE',
            'path' => fn (array $ctx): string => '/api/v1/instructor/assessment/questions/'.$ctx['fixtures']['questionA1Id'],
            'expect' => ['status' => 422, 'json' => ['errors.0.code' => 'validation_error']],
        ],
        [
            'name' => 'Parent cross-tenant falha antes de criar',
            'as' => 'instructor',
            'tenant' => 'other',
            'method' => 'POST',
            'path' => '/api/v1/instructor/assessment/questionnaires',
            'body' => ['title' => 'cross tenant', 'type' => 'course', 'quizable_type' => 'course', 'quizable_id' => fn (array $ctx): int => $ctx['fixtures']['foreignCourseId']],
            'expect' => ['status' => 403, 'json' => ['errors.0.code' => 'access_denied']],
        ],
    ],

    'cleanup' => function (array $ctx): void {
        /** @var User|null $instructorB */
        $instructorB = $ctx['fixtures']['instructorB'] ?? null;
        if ($instructorB instanceof User) {
            $instructorB->tokens()->delete();
            $instructorB->forceDelete();
        }
    },
];
