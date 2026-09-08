<?php

namespace App\Modules\Assessment\Actions\Questionnaire;

use App\Modules\Assessment\Http\Requests\Instructor\ReorderQuestionsRequest;
use App\Modules\Assessment\Services\InstructorAssessmentScope;
use App\Shared\Http\ApiContext;
use Illuminate\Validation\ValidationException;

class ReorderInstructorQuestionsAction
{
    public function __construct(
        private readonly InstructorAssessmentScope $scope,
    ) {}

    public function handle(ReorderQuestionsRequest $request, int $questionnaireId, ApiContext $context): void
    {
        $questionnaire = $this->scope->questionnaire($context, $questionnaireId);
        $this->scope->assertQuestionnaireHasNoAttempts($questionnaire);
        $requestedIds = array_map('intval', $request->validated('question_ids'));
        $currentIds = $questionnaire->questions()->pluck('quiz_question_id')->map(static fn ($id): int => (int) $id)->all();
        $expectedIds = $currentIds;
        sort($expectedIds);
        $actualIds = $requestedIds;
        sort($actualIds);

        if ($expectedIds !== $actualIds) {
            throw ValidationException::withMessages([
                'question_ids' => ['Reorder must contain exactly the questionnaire question set.'],
            ]);
        }

        $connection = $questionnaire->questions()->getQuery()->getConnection();
        $connection->transaction(function () use ($questionnaire, $requestedIds): void {
            foreach ($requestedIds as $position => $questionId) {
                $questionnaire->questions()
                    ->where('quiz_question_id', $questionId)
                    ->update(['sort_order' => $position + 1]);
            }
        });
    }
}
