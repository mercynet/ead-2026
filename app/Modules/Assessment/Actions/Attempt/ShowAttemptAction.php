<?php

namespace App\Modules\Assessment\Actions\Attempt;

use App\Modules\Assessment\Models\QuizAttempt;
use App\Shared\Http\ApiContext;

/**
 * Get an attempt by ID.
 *
 * @apiResource App\Modules\Assessment\Http\Resources\AttemptResource
 *
 * @apiResourceModel App\Modules\Assessment\Models\QuizAttempt
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
