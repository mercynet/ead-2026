<?php

namespace App\Actions\Assessment\Question;

use App\Http\Context\ApiContext;
use App\Models\QuizQuestion;
use Illuminate\Http\Request;

/**
 * Update a question.
 *
 * @apiResource App\Http\Resources\Assessment\QuestionResource
 *
 * @apiResourceModel App\Models\QuizQuestion
 *
 * @bodyParam question string The question text.
 * @bodyParam type string The question type (single_choice|multiple_choice|true_false).
 * @bodyParam options array Array of options with 'text' and 'correct' keys.
 * @bodyParam explanation string|null Explanation for the correct answer.
 * @bodyParam points int Points for this question.
 * @bodyParam is_active bool Whether the question is active.
 * @bodyParam category_ids array|null Array of category IDs to associate.
 */
class UpdateQuestionAction
{
    public function handle(Request $request, int $id, ApiContext $context): QuizQuestion
    {
        $query = QuizQuestion::query();

        if ($context->tenant !== null) {
            $query->where('tenant_id', $context->tenant->id);
        }

        $question = $query->findOrFail($id);

        $hasAttempts = $question->questionnaires()
            ->whereHas('attempts', fn ($q) => $q->where('status', 'completed'))
            ->exists();

        if ($hasAttempts) {
            abort(422, 'Cannot edit a question that has been used in completed attempts. Create a new version instead.');
        }

        $data = $request->validated();

        if (isset($data['category_ids'])) {
            $question->categories()->sync($data['category_ids']);
            unset($data['category_ids']);
        }

        $question->update($data);

        return $question->fresh(['categories', 'instructor']);
    }
}
