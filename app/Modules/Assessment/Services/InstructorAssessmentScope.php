<?php

namespace App\Modules\Assessment\Services;

use App\Modules\Assessment\Models\Questionnaire;
use App\Modules\Assessment\Models\QuizQuestion;
use App\Modules\Learning\Contracts\AssessmentCatalog;
use App\Shared\Http\ApiContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;

final class InstructorAssessmentScope
{
    public function __construct(
        private readonly AssessmentCatalog $assessmentCatalog,
    ) {}

    /**
     * @return Builder<Questionnaire>
     */
    public function questionnaires(ApiContext $context): Builder
    {
        $tenant = $context->requiredTenant();
        $instructor = $context->requiredUser();
        $courseIds = $this->assessmentCatalog->ownedParentIds('course', (int) $tenant->id, (int) $instructor->id);
        $lessonIds = $this->assessmentCatalog->ownedParentIds('lesson', (int) $tenant->id, (int) $instructor->id);

        return Questionnaire::query()
            ->where('tenant_id', $tenant->id)
            ->where('instructor_id', $instructor->id)
            ->where(function (Builder $query) use ($courseIds, $lessonIds): void {
                $query
                    ->where(function (Builder $courseQuery) use ($courseIds): void {
                        $courseQuery->where('type', 'course')->whereIn('quizable_id', $courseIds);
                    })
                    ->orWhere(function (Builder $lessonQuery) use ($lessonIds): void {
                        $lessonQuery->where('type', 'lesson')->whereIn('quizable_id', $lessonIds);
                    });
            });
    }

    public function questionnaire(ApiContext $context, int $id, bool $withQuestions = false): Questionnaire
    {
        $query = $this->questionnaires($context);

        if ($withQuestions) {
            $query->with(['questions' => fn ($questionQuery) => $questionQuery
                ->with('question.categories')
                ->orderBy('sort_order')
                ->orderBy('id')]);
        }

        return $query->findOrFail($id);
    }

    /**
     * @return Builder<QuizQuestion>
     */
    public function questions(ApiContext $context): Builder
    {
        $tenant = $context->requiredTenant();
        $instructor = $context->requiredUser();

        return QuizQuestion::query()
            ->where('tenant_id', $tenant->id)
            ->where('instructor_id', $instructor->id);
    }

    public function question(ApiContext $context, int $id): QuizQuestion
    {
        return $this->questions($context)
            ->with(['categories:id,name,slug'])
            ->findOrFail($id);
    }

    public function assertQuestionnaireHasNoAttempts(Questionnaire $questionnaire): void
    {
        if ($questionnaire->attempts()->exists()) {
            throw ValidationException::withMessages([
                'questionnaire' => ['This questionnaire is immutable after its first attempt.'],
            ]);
        }
    }

    public function assertQuestionHasNoAttempts(QuizQuestion $question): void
    {
        if ($question->questionnaires()->whereHas('attempts')->exists()) {
            throw ValidationException::withMessages([
                'question' => ['This question is immutable after its first attempt.'],
            ]);
        }
    }
}
