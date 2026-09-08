<?php

namespace App\Modules\Assessment\Actions\Question;

use App\Modules\Assessment\Http\Requests\Instructor\UpdateQuestionRequest;
use App\Modules\Assessment\Models\QuizQuestion;
use App\Modules\Assessment\Services\InstructorAssessmentScope;
use App\Modules\Learning\Contracts\AssessmentCatalog;
use App\Shared\Http\ApiContext;
use Illuminate\Validation\ValidationException;

class UpdateInstructorQuestionAction
{
    public function __construct(
        private readonly InstructorAssessmentScope $scope,
        private readonly AssessmentCatalog $assessmentCatalog,
    ) {}

    public function handle(UpdateQuestionRequest $request, int $id, ApiContext $context): QuizQuestion
    {
        $question = $this->scope->question($context, $id);
        $this->scope->assertQuestionHasNoAttempts($question);
        $data = $request->validated();

        if (array_key_exists('category_ids', $data)) {
            $categoryIds = array_map('intval', $data['category_ids'] ?? []);
            $availableCategoryIds = $this->assessmentCatalog->categoryIdsAvailableForTenant(
                $categoryIds,
                (int) $context->requiredTenant()->id,
            );

            if (count($availableCategoryIds) !== count($categoryIds)) {
                throw ValidationException::withMessages([
                    'category_ids' => ['Every category must be global or belong to this tenant.'],
                ]);
            }

            $question->categories()->sync($availableCategoryIds);
            unset($data['category_ids']);
        }

        $question->update($data);

        return $question->fresh(['categories', 'instructor']);
    }
}
