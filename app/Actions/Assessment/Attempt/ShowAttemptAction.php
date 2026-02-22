<?php

namespace App\Actions\Assessment\Attempt;

use App\Http\Context\ApiContext;
use App\Models\QuizAttempt;

/**
 * Get an attempt by ID.
 *
 * @apiResource App\Http\Resources\Assessment\AttemptResource
 *
 * @apiResourceModel App\Models\QuizAttempt
 */
class ShowAttemptAction
{
    public function handle(int $id, ApiContext $context): QuizAttempt
    {
        return QuizAttempt::query()
            ->with(['answers'])
            ->where('user_id', $context->user->id)
            ->findOrFail($id);
    }
}
