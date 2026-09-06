<?php

namespace App\Modules\Assessment\Actions\Questionnaire;

use App\Modules\Assessment\Models\Questionnaire;
use App\Shared\Http\ApiContext;
use Illuminate\Validation\ValidationException;

/**
 * Delete a questionnaire.
 *
 * @response 204 {}
 */
class DeleteQuestionnaireAction
{
    public function handle(int $id, ApiContext $context): void
    {
        $query = Questionnaire::query();

        if ($context->tenant !== null) {
            $query->where('tenant_id', $context->tenant->id);
        }

        $questionnaire = $query->findOrFail($id);

        $hasAttempts = $questionnaire->attempts()->exists();
        if ($hasAttempts) {
            throw ValidationException::withMessages([
                'questionnaire' => ['Cannot delete a questionnaire that has attempts.'],
            ]);
        }

        $questionnaire->delete();
    }
}
