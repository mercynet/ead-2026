<?php

namespace App\Modules\Assessment\Actions\Questionnaire;

use App\Modules\Assessment\Http\Requests\Instructor\UpdateQuestionnaireRequest;
use App\Modules\Assessment\Models\Questionnaire;
use App\Modules\Assessment\Services\InstructorAssessmentScope;
use App\Shared\Http\ApiContext;

class UpdateInstructorQuestionnaireAction
{
    public function __construct(
        private readonly InstructorAssessmentScope $scope,
    ) {}

    public function handle(UpdateQuestionnaireRequest $request, int $id, ApiContext $context): Questionnaire
    {
        $questionnaire = $this->scope->questionnaire($context, $id);
        $this->scope->assertQuestionnaireHasNoAttempts($questionnaire);
        $questionnaire->update($request->validated());

        return $questionnaire->fresh();
    }
}
