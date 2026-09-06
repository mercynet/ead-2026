<?php

namespace App\Modules\Assessment\Actions\Question;

use App\Modules\Assessment\Http\Requests\StoreQuestionRequest;
use App\Modules\Assessment\Models\QuizQuestion;
use App\Modules\Learning\Contracts\AssessmentCatalog;
use App\Shared\Http\ApiContext;
use Illuminate\Validation\ValidationException;

class StoreAdminQuestionAction
{
    public function __construct(
        private readonly AssessmentCatalog $assessmentCatalog,
    ) {}

    public function handle(StoreQuestionRequest $request, ApiContext $context): QuizQuestion
    {
        $tenant = $context->requiredTenant();
        $data = $request->validated();
        $categoryIds = array_map('intval', $data['category_ids'] ?? []);
        unset($data['category_ids']);

        $data['tenant_id'] = $tenant->id;
        $data['instructor_id'] = null;

        $availableCategoryIds = $this->assessmentCatalog->categoryIdsAvailableForTenant(
            $categoryIds,
            (int) $tenant->id,
        );

        if (count($availableCategoryIds) !== count(array_unique($categoryIds))) {
            throw ValidationException::withMessages([
                'category_ids' => ['Every category must be global or belong to this tenant.'],
            ]);
        }

        $question = QuizQuestion::query()->create($data);
        $question->categories()->sync($availableCategoryIds);

        return $question->load(['categories', 'instructor']);
    }
}
