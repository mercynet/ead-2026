<?php

namespace App\Modules\Assessment\Actions\Questionnaire;

use App\Modules\Assessment\Http\Requests\Instructor\StoreQuestionnaireRequest;
use App\Modules\Assessment\Models\Questionnaire;
use App\Modules\Learning\Contracts\AssessmentCatalog;
use App\Shared\Http\ApiContext;
use Illuminate\Validation\ValidationException;

class StoreInstructorQuestionnaireAction
{
    public function __construct(
        private readonly AssessmentCatalog $assessmentCatalog,
    ) {}

    public function handle(StoreQuestionnaireRequest $request, ApiContext $context): Questionnaire
    {
        $data = $request->validated();
        $tenant = $context->requiredTenant();
        $instructor = $context->requiredUser();

        if (! $this->assessmentCatalog->parentBelongsToInstructor(
            (string) $data['quizable_type'],
            (int) $data['quizable_id'],
            (int) $tenant->id,
            (int) $instructor->id,
        )) {
            throw ValidationException::withMessages([
                'quizable_id' => ['The linked parent is not owned by this instructor.'],
            ]);
        }

        return Questionnaire::query()->create([
            ...$data,
            'tenant_id' => $tenant->id,
            'instructor_id' => $instructor->id,
        ]);
    }
}
