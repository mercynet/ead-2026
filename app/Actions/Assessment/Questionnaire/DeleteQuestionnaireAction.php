<?php

namespace App\Actions\Assessment\Questionnaire;

use App\Http\Context\ApiContext;
use App\Models\Questionnaire;

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
            abort(422, 'Cannot delete a questionnaire that has attempts.');
        }

        $questionnaire->delete();
    }
}
