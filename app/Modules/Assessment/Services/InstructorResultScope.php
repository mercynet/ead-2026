<?php

namespace App\Modules\Assessment\Services;

use App\Modules\Assessment\Models\QuizAttempt;
use App\Modules\Learning\Contracts\AssessmentCatalog;
use App\Shared\Http\ApiContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

final class InstructorResultScope
{
    public function __construct(
        private readonly InstructorAssessmentScope $assessmentScope,
        private readonly AssessmentCatalog $assessmentCatalog,
    ) {}

    /**
     * @return array<int, int> questionnaire ID => owned Course ID
     */
    public function questionnaireCourseMap(ApiContext $context): array
    {
        $questionnaires = $this->assessmentScope->questionnaires($context)
            ->get(['id', 'type', 'quizable_id']);
        $parentIds = ['course' => [], 'lesson' => []];

        foreach ($questionnaires as $questionnaire) {
            $type = (string) $questionnaire->getAttribute('type');

            if (array_key_exists($type, $parentIds)) {
                $parentIds[$type][] = (int) $questionnaire->getAttribute('quizable_id');
            }
        }

        $tenantId = (int) $context->requiredTenant()->id;
        $courseIdsByParent = [
            'course' => $this->assessmentCatalog->courseIdsForParentIds(
                'course',
                array_values(array_unique($parentIds['course'])),
                $tenantId,
            ),
            'lesson' => $this->assessmentCatalog->courseIdsForParentIds(
                'lesson',
                array_values(array_unique($parentIds['lesson'])),
                $tenantId,
            ),
        ];
        $map = [];

        foreach ($questionnaires as $questionnaire) {
            $type = (string) $questionnaire->getAttribute('type');
            $parentId = (int) $questionnaire->getAttribute('quizable_id');
            $courseId = $courseIdsByParent[$type][$parentId] ?? null;

            if ($courseId !== null) {
                $map[(int) $questionnaire->id] = $courseId;
            }
        }

        return $map;
    }

    /**
     * @return Builder<QuizAttempt>
     */
    public function attempts(ApiContext $context): Builder
    {
        $tenantId = (int) $context->requiredTenant()->id;
        $pairs = [];
        $questionnaireCourseMap = $this->questionnaireCourseMap($context);
        $enrolledUserIds = $this->assessmentCatalog->enrolledUserIdsForCourses(
            array_values(array_unique(array_values($questionnaireCourseMap))),
            $tenantId,
        );

        foreach ($questionnaireCourseMap as $questionnaireId => $courseId) {
            $userIds = $enrolledUserIds[$courseId] ?? [];
            if ($userIds !== []) {
                $pairs[$questionnaireId] = $userIds;
            }
        }

        $query = QuizAttempt::query()
            ->where('tenant_id', $tenantId)
            ->with([
                'questionnaire:id,title,type,quizable_id,quizable_type',
                'user:id,name,avatar',
                'answers',
            ]);

        if ($pairs === []) {
            return $query->whereKey(0);
        }

        return $query
            ->where(function (Builder $pairQuery) use ($pairs): void {
                foreach ($pairs as $questionnaireId => $userIds) {
                    $pairQuery->orWhere(function (Builder $query) use ($questionnaireId, $userIds): void {
                        $query->where('questionnaire_id', $questionnaireId)->whereIn('user_id', $userIds);
                    });
                }
            })
            ->orderBy('id');
    }

    public function attempt(ApiContext $context, int $id): QuizAttempt
    {
        return $this->attempts($context)->findOrFail($id);
    }

    /**
     * @param  Collection<int, QuizAttempt>  $attempts
     */
    public function attachAttemptNumbers(Collection $attempts, ApiContext $context): void
    {
        if ($attempts->isEmpty()) {
            return;
        }

        $tenantId = (int) $context->requiredTenant()->id;
        $questionnaireIds = $attempts->map(static fn (QuizAttempt $attempt): int => (int) $attempt->getAttribute('questionnaire_id'))->unique()->values();
        $userIds = $attempts->map(static fn (QuizAttempt $attempt): int => (int) $attempt->getAttribute('user_id'))->unique()->values();
        $counts = QuizAttempt::query()
            ->select(['questionnaire_id', 'user_id'])
            ->selectRaw('COUNT(*) as attempt_number')
            ->where('tenant_id', $tenantId)
            ->whereIn('questionnaire_id', $questionnaireIds)
            ->whereIn('user_id', $userIds)
            ->groupBy('questionnaire_id', 'user_id')
            ->get()
            ->keyBy(fn (QuizAttempt $attempt): string => $attempt->getAttribute('questionnaire_id').':'.$attempt->getAttribute('user_id'));

        $attempts->each(function (QuizAttempt $attempt) use ($counts): void {
            $count = $counts->get($attempt->getAttribute('questionnaire_id').':'.$attempt->getAttribute('user_id'));
            $attempt->setAttribute('attempt_number', $count === null ? 1 : (int) $count->getAttribute('attempt_number'));
        });
    }
}
