<?php

namespace App\Modules\Assessment\Actions\Questionnaire;

use App\Modules\Assessment\Models\Questionnaire;
use App\Shared\Http\ApiContext;
use Illuminate\Http\Request;

/**
 * Update a questionnaire.
 *
 * @apiResource App\Modules\Assessment\Http\Resources\QuestionnaireResource
 *
 * @apiResourceModel App\Modules\Assessment\Models\Questionnaire
 *
 * @bodyParam title string The questionnaire title.
 * @bodyParam description string|null The questionnaire description.
 * @bodyParam passing_score int The minimum score to pass (0-100).
 * @bodyParam time_limit_minutes int|null Time limit in minutes.
 * @bodyParam is_active bool Whether the questionnaire is active.
 * @bodyParam show_results bool Whether to show results to students.
 */
class UpdateQuestionnaireAction
{
    public function handle(Request $request, int $id, ApiContext $context): Questionnaire
    {
        $query = Questionnaire::query();

        if ($context->tenant !== null) {
            $query->where('tenant_id', $context->tenant->id);
        }

        $questionnaire = $query->findOrFail($id);
        $questionnaire->update($request->validated());

        return $questionnaire->fresh();
    }
}
