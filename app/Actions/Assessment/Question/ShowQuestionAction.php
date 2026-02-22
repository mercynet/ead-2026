<?php

namespace App\Actions\Assessment\Question;

use App\Http\Context\ApiContext;
use App\Models\QuizQuestion;

/**
 * Get a question by ID.
 *
 * @apiResource App\Http\Resources\Assessment\QuestionResource
 *
 * @apiResourceModel App\Models\QuizQuestion
 */
class ShowQuestionAction
{
    public function handle(int $id, ApiContext $context): QuizQuestion
    {
        $query = QuizQuestion::query()
            ->with(['categories:id,name,slug', 'instructor:id,name,email']);

        if ($context->tenant !== null) {
            $query->where('tenant_id', $context->tenant->id);
        }

        return $query->findOrFail($id);
    }
}
