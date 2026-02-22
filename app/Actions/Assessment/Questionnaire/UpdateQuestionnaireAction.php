<?php

namespace App\Actions\Assessment\Questionnaire;

use App\Http\Context\ApiContext;
use App\Models\Questionnaire;
use Illuminate\Http\Request;

/**
 * Update a questionnaire.
 *
 * @apiResource App\Http\Resources\Assessment\QuestionnaireResource
 *
 * @apiResourceModel App\Models\Questionnaire
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
