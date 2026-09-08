<?php

namespace App\Modules\Assessment\Actions\Questionnaire;

use App\Modules\Assessment\Models\Questionnaire;
use App\Modules\Assessment\Services\InstructorAssessmentScope;
use App\Shared\Http\ApiContext;

class ShowInstructorQuestionnaireAction
{
    public function __construct(
        private readonly InstructorAssessmentScope $scope,
    ) {}

    public function handle(int $id, ApiContext $context, bool $withQuestions = false): Questionnaire
    {
        return $this->scope->questionnaire($context, $id, $withQuestions);
    }
}
