<?php

namespace App\Modules\Assessment\Actions\Questionnaire;

use App\Modules\Assessment\Http\Requests\AttachQuestionsRequest;
use App\Modules\Assessment\Models\Questionnaire;
use App\Modules\Assessment\Models\QuestionnaireQuestion;
use App\Modules\Assessment\Models\QuizQuestion;
use App\Shared\Http\ApiContext;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\ValidationException;

class AttachQuestionsAction
{
    public function handle(AttachQuestionsRequest $request, int $questionnaireId, ApiContext $context): void
    {
        $query = Questionnaire::query();

        if ($context->tenant !== null) {
            $query->where('tenant_id', $context->tenant->id);
        }

        $questionnaire = $query->findOrFail($questionnaireId);

        if ($questionnaire->attempts()->exists()) {
            throw ValidationException::withMessages([
                'questionnaire' => ['This questionnaire is immutable after its first attempt.'],
            ]);
        }

        $questionIds = array_map('intval', $request->validated('question_ids'));
        $availableQuestionIds = QuizQuestion::query()
            ->where('tenant_id', $questionnaire->getAttribute('tenant_id'))
            ->whereIn('id', $questionIds)
            ->pluck('id')
            ->map(static fn ($id): int => (int) $id)
            ->all();

        if (count($availableQuestionIds) !== count($questionIds)) {
            throw (new ModelNotFoundException)->setModel(QuizQuestion::class);
        }

        $existingIds = $questionnaire->questions()
            ->whereIn('quiz_question_id', $questionIds)
            ->pluck('quiz_question_id')
            ->all();

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
}
