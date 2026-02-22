<?php

namespace App\Actions\Assessment\Question;

use App\Http\Context\ApiContext;
use App\Models\QuizQuestion;
use Illuminate\Http\Request;

/**
 * Create a new question.
 *
 * @apiResource App\Http\Resources\Assessment\QuestionResource
 *
 * @apiResourceModel App\Models\QuizQuestion
 *
 * @bodyParam question string required The question text. Example: Qual é a capital do Brasil?
 * @bodyParam type string required The question type (single_choice|multiple_choice|true_false).
 * @bodyParam options array required Array of options with 'text' and 'correct' keys.
 * @bodyParam explanation string|null Explanation for the correct answer.
 * @bodyParam points int Points for this question. Default: 1.
 * @bodyParam is_active bool Whether the question is active. Default: true.
 * @bodyParam category_ids array|null Array of category IDs to associate.
 */
class StoreQuestionAction
{
    public function handle(Request $request, ApiContext $context): QuizQuestion
    {
        $data = $request->validated();

        $data['tenant_id'] = $context->tenant->id;
        $data['instructor_id'] = $context->user->id;

        $question = QuizQuestion::create($data);

        if (! empty($data['category_ids'])) {
            $question->categories()->attach($data['category_ids']);
        }

        return $question->fresh(['categories', 'instructor']);
    }
}
