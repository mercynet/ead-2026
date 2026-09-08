<?php

namespace App\Modules\Assessment\Actions\Question;

use App\Modules\Assessment\Http\Requests\Instructor\StoreQuestionRequest;
use App\Modules\Assessment\Models\QuizQuestion;
use App\Modules\Learning\Contracts\AssessmentCatalog;
use App\Shared\Http\ApiContext;
use Illuminate\Validation\ValidationException;

class StoreInstructorQuestionAction
{
    public function __construct(
        private readonly AssessmentCatalog $assessmentCatalog,
    ) {}

    public function handle(StoreQuestionRequest $request, ApiContext $context): QuizQuestion
    {
        $data = $request->validated();
        $tenant = $context->requiredTenant();
        $instructor = $context->requiredUser();
        $categoryIds = array_map('intval', $data['category_ids'] ?? []);
        unset($data['category_ids']);

        $availableCategoryIds = $this->assessmentCatalog->categoryIdsAvailableForTenant($categoryIds, (int) $tenant->id);
        if (count($availableCategoryIds) !== count($categoryIds)) {
            throw ValidationException::withMessages([
                'category_ids' => ['Every category must be global or belong to this tenant.'],
            ]);
        }

        $question = QuizQuestion::query()->create([
            ...$data,
            'tenant_id' => $tenant->id,
            'instructor_id' => $instructor->id,
        ]);
        $question->categories()->sync($availableCategoryIds);

        return $question->load(['categories', 'instructor']);
    }
}
