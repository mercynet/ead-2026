<?php

namespace App\Modules\Assessment\Actions\Question;

use App\Modules\Assessment\Models\QuizQuestion;
use App\Modules\Assessment\Services\InstructorAssessmentScope;
use App\Shared\Http\ApiContext;

class ShowInstructorQuestionAction
{
    public function __construct(
        private readonly InstructorAssessmentScope $scope,
    ) {}

    public function handle(int $id, ApiContext $context): QuizQuestion
    {
        return $this->scope->question($context, $id);
    }
}
