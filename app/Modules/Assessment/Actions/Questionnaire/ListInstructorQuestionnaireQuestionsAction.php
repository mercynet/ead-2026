<?php

namespace App\Modules\Assessment\Actions\Questionnaire;

use App\Modules\Assessment\Models\QuestionnaireQuestion;
use App\Modules\Assessment\Services\InstructorAssessmentScope;
use App\Shared\Http\ApiContext;
use Illuminate\Database\Eloquent\Collection;

class ListInstructorQuestionnaireQuestionsAction
{
    public function __construct(
        private readonly InstructorAssessmentScope $scope,
    ) {}

    /**
     * @return Collection<int, QuestionnaireQuestion>
     */
    public function handle(int $questionnaireId, ApiContext $context): Collection
    {
        $questionnaire = $this->scope->questionnaire($context, $questionnaireId);

        /** @var Collection<int, QuestionnaireQuestion> $questions */
        $questions = $questionnaire->questions()
            ->with(['question.categories:id,name,slug'])
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        return $questions;
    }
}
