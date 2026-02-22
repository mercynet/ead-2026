<?php

namespace App\Actions\Assessment\Questionnaire;

use App\Enums\QuestionnaireType;
use App\Http\Context\ApiContext;
use App\Models\Questionnaire;
use Illuminate\Http\Request;
use Illuminate\Pagination\CursorPaginator;

/**
 * List all questionnaires for the tenant.
 *
 * @apiResource App\Http\Resources\Assessment\QuestionnaireResource
 *
 * @apiResourceCollection Illuminate\Pagination\CursorPaginator<App\Http\Resources\Assessment\QuestionnaireResource>
 */
class ListQuestionnairesAction
{
    public function handle(Request $request, ApiContext $context): CursorPaginator
    {
        $query = Questionnaire::query()
            ->with(['instructor:id,name,email'])
            ->orderBy('id');

        if ($context->tenant !== null) {
            $query->where('tenant_id', $context->tenant->id);
        }

        $type = $request->query('type');
        if ($type !== null) {
            $validTypes = array_column(QuestionnaireType::cases(), 'value');
            if (in_array($type, $validTypes, true)) {
                $query->where('type', $type);
            }
        }

        $isActive = $request->query('is_active');
        if ($isActive !== null) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        return $query->cursorPaginate(15);
    }
}
