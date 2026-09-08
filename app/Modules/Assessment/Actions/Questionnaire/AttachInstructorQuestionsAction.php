<?php

namespace App\Modules\Assessment\Actions\Questionnaire;

use App\Modules\Assessment\Http\Requests\Instructor\AttachQuestionsRequest;
use App\Modules\Assessment\Models\QuestionnaireQuestion;
use App\Modules\Assessment\Models\QuizQuestion;
use App\Modules\Assessment\Services\InstructorAssessmentScope;
use App\Shared\Http\ApiContext;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\ValidationException;

class AttachInstructorQuestionsAction
{
    public function __construct(
        private readonly InstructorAssessmentScope $scope,
    ) {}

    public function handle(AttachQuestionsRequest $request, int $questionnaireId, ApiContext $context): void
    {
        $questionnaire = $this->scope->questionnaire($context, $questionnaireId);
        $this->scope->assertQuestionnaireHasNoAttempts($questionnaire);
        $questionIds = array_map('intval', $request->validated('question_ids'));
        $ownedQuestionIds = $this->scope->questions($context)->whereIn('id', $questionIds)->pluck('id')->map(static fn ($id): int => (int) $id)->all();

        if (count($ownedQuestionIds) !== count($questionIds)) {
            throw $this->notFound();
        }

        $existingIds = $questionnaire->questions()->whereIn('quiz_question_id', $questionIds)->pluck('quiz_question_id')->all();
        if ($existingIds !== []) {
            throw ValidationException::withMessages([
                'question_ids' => ['A question cannot be attached twice to the same questionnaire.'],
            ]);
        }

        $nextSortOrder = (int) ($questionnaire->questions()->max('sort_order') ?? 0) + 1;
        $connection = QuestionnaireQuestion::query()->getConnection();
        $connection->transaction(function () use ($questionnaire, $questionIds, $nextSortOrder): void {
            foreach ($questionIds as $offset => $questionId) {
                QuestionnaireQuestion::query()->create([
                    'questionnaire_id' => $questionnaire->id,
                    'quiz_question_id' => $questionId,
                    'sort_order' => $nextSortOrder + $offset,
                ]);
            }
        });
    }

    private function notFound(): ModelNotFoundException
    {
        return (new ModelNotFoundException)->setModel(QuizQuestion::class);
    }
}
