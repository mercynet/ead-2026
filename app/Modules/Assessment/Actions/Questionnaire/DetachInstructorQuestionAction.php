<?php

namespace App\Modules\Assessment\Actions\Questionnaire;

use App\Modules\Assessment\Services\InstructorAssessmentScope;
use App\Shared\Http\ApiContext;

class DetachInstructorQuestionAction
{
    public function __construct(
        private readonly InstructorAssessmentScope $scope,
    ) {}

    public function handle(int $questionnaireId, int $questionId, ApiContext $context): void
    {
        $questionnaire = $this->scope->questionnaire($context, $questionnaireId);
        $this->scope->assertQuestionnaireHasNoAttempts($questionnaire);

        $questionnaire->questions()
            ->where('quiz_question_id', $questionId)
            ->firstOrFail()
            ->delete();
    }
}
