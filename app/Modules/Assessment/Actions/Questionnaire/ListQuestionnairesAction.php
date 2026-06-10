<?php

namespace App\Modules\Assessment\Actions\Questionnaire;

use App\Modules\Assessment\Enums\QuestionnaireType;
use App\Modules\Assessment\Models\Questionnaire;
use App\Shared\Http\ApiContext;
use Illuminate\Http\Request;
use Illuminate\Pagination\CursorPaginator;

/**
 * List all questionnaires for the tenant.
 *
 * @apiResource App\Modules\Assessment\Http\Resources\QuestionnaireResource
 *
 * @apiResourceCollection Illuminate\Pagination\CursorPaginator<App\Modules\Assessment\Http\Resources\QuestionnaireResource>
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
