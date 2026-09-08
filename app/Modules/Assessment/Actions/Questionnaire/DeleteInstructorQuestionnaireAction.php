<?php

namespace App\Modules\Assessment\Actions\Questionnaire;

use App\Modules\Assessment\Services\InstructorAssessmentScope;
use App\Shared\Http\ApiContext;

class DeleteInstructorQuestionnaireAction
{
    public function __construct(
        private readonly InstructorAssessmentScope $scope,
    ) {}

    public function handle(int $id, ApiContext $context): void
    {
        $questionnaire = $this->scope->questionnaire($context, $id);
        $this->scope->assertQuestionnaireHasNoAttempts($questionnaire);
        $questionnaire->delete();
    }
}
