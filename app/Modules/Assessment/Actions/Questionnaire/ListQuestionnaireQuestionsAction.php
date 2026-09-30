<?php

namespace App\Modules\Assessment\Actions\Questionnaire;

use App\Modules\Assessment\Models\Questionnaire;
use App\Modules\Assessment\Models\QuestionnaireQuestion;
use App\Shared\Http\ApiContext;
use Illuminate\Database\Eloquent\Collection;

class ListQuestionnaireQuestionsAction
{
    /**
     * @return Collection<int, QuestionnaireQuestion>
     */
    public function handle(int $questionnaireId, ApiContext $context): Collection
    {
        $query = Questionnaire::query();

        if ($context->tenant !== null) {
            $query->where('tenant_id', $context->tenant->id);
        }

        $questionnaire = $query->findOrFail($questionnaireId);

        /** @var Collection<int, QuestionnaireQuestion> $questions */
        $questions = $questionnaire->questions()
            ->with(['question.categories:id,name,slug', 'question.instructor:id,name,email'])
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        return $questions;
    }
}
