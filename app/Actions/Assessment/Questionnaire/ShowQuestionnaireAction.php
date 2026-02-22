<?php

namespace App\Actions\Assessment\Questionnaire;

use App\Http\Context\ApiContext;
use App\Models\Questionnaire;

/**
 * Get a questionnaire by ID.
 *
 * @apiResource App\Http\Resources\Assessment\QuestionnaireResource
 *
 * @apiResourceModel App\Models\Questionnaire
 */
class ShowQuestionnaireAction
{
    public function handle(int $id, ApiContext $context): Questionnaire
    {
        $query = Questionnaire::query()
            ->with(['instructor:id,name,email']);

        if ($context->tenant !== null) {
            $query->where('tenant_id', $context->tenant->id);
        }

        return $query->findOrFail($id);
    }
}
