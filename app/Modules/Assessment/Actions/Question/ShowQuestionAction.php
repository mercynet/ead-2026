<?php

namespace App\Modules\Assessment\Actions\Question;

use App\Modules\Assessment\Models\QuizQuestion;
use App\Shared\Http\ApiContext;

/**
 * Get a question by ID.
 *
 * @apiResource App\Modules\Assessment\Http\Resources\QuestionResource
 *
 * @apiResourceModel App\Modules\Assessment\Models\QuizQuestion
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
