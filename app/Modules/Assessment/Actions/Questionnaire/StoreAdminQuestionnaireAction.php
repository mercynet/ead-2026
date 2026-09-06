<?php

namespace App\Modules\Assessment\Actions\Questionnaire;

use App\Modules\Assessment\Http\Requests\StoreQuestionnaireRequest;
use App\Modules\Assessment\Models\Questionnaire;
use App\Modules\Learning\Contracts\AssessmentCatalog;
use App\Shared\Http\ApiContext;
use Illuminate\Validation\ValidationException;

class StoreAdminQuestionnaireAction
{
    public function __construct(
        private readonly AssessmentCatalog $assessmentCatalog,
    ) {}

    public function handle(StoreQuestionnaireRequest $request, ApiContext $context): Questionnaire
    {
        $tenant = $context->requiredTenant();
        $data = $request->validated();
        $data['tenant_id'] = $tenant->id;
        $data['instructor_id'] = null;

        if (! empty($data['quizable_id']) && ! empty($data['quizable_type'])) {
            if (! $this->assessmentCatalog->parentBelongsToTenant(
                (string) $data['quizable_type'],
                (int) $data['quizable_id'],
                (int) $tenant->id,
            )) {
                throw ValidationException::withMessages([
                    'quizable_id' => ['The linked assessment parent does not belong to this tenant.'],
                ]);
            }
        }

        return Questionnaire::query()->create($data);
    }
}
