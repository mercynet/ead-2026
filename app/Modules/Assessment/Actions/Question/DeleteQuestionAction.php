<?php

namespace App\Modules\Assessment\Actions\Question;

use App\Modules\Assessment\Models\QuizQuestion;
use App\Shared\Http\ApiContext;
use Illuminate\Validation\ValidationException;

class DeleteQuestionAction
{
    public function handle(int $id, ApiContext $context): void
    {
        $query = QuizQuestion::query();

        if ($context->tenant !== null) {
            $query->where('tenant_id', $context->tenant->id);
        }

        $question = $query->findOrFail($id);

        if ($question->questionnaires()->whereHas('attempts')->exists()) {
            throw ValidationException::withMessages([
                'question' => ['This question is immutable after its first attempt.'],
            ]);
        }

        $question->delete();
    }
}
