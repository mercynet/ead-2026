<?php

namespace App\Modules\Assessment\Actions\Questionnaire;

use App\Modules\Assessment\Models\Questionnaire;
use App\Shared\Http\ApiContext;

/**
 * Get a questionnaire by ID.
 *
 * @apiResource App\Modules\Assessment\Http\Resources\QuestionnaireResource
 *
 * @apiResourceModel App\Modules\Assessment\Models\Questionnaire
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
