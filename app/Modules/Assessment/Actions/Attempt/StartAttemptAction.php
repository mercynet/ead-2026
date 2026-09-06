<?php

namespace App\Modules\Assessment\Actions\Attempt;

use App\Modules\Assessment\Models\Questionnaire;
use App\Modules\Assessment\Models\QuizAttempt;
use App\Modules\Learning\Models\Course;
use App\Modules\Learning\Models\CourseModule;
use App\Shared\Http\ApiContext;
use Illuminate\Validation\ValidationException;

/**
 * Start a new attempt for a questionnaire.
 *
 * @apiResource App\Modules\Assessment\Http\Resources\AttemptResource
 *
 * @apiResourceModel App\Modules\Assessment\Models\QuizAttempt
 */
class StartAttemptAction
{
    public function handle(int $questionnaireId, ApiContext $context): QuizAttempt
    {
        $questionnaire = Questionnaire::query()
            ->with(['instructor', 'questions.question'])
            ->where('tenant_id', $context->tenant->id)
            ->findOrFail($questionnaireId);

        if (! $questionnaire->is_active) {
            throw ValidationException::withMessages([
                'questionnaire' => ['This questionnaire is not active.'],
            ]);
        }

        $questionsSnapshot = $this->freezeQuestions($questionnaire);

        if ($questionsSnapshot === []) {
            throw ValidationException::withMessages([
                'questionnaire' => ['This questionnaire has no active questions.'],
            ]);
        }

        $hasInProgressAttempt = $context->user->attempts()
            ->where('questionnaire_id', $questionnaireId)
            ->where('status', 'in_progress')
            ->exists();

        if ($hasInProgressAttempt) {
            throw ValidationException::withMessages([
                'questionnaire' => ['You already have an in-progress attempt for this questionnaire.'],
            ]);
        }

        $questionnaireSnapshot = [
            'id' => $questionnaire->id,
            'title' => $questionnaire->title,
            'description' => $questionnaire->description,
            'type' => $questionnaire->type,
            'passing_score' => $questionnaire->passing_score,
            'time_limit_minutes' => $questionnaire->time_limit_minutes,
            'show_results' => $questionnaire->show_results,
        ];

        $courseSnapshot = null;
        $moduleSnapshot = null;

        if ($questionnaire->quizable_type === Course::class && $questionnaire->quizable_id) {
            $course = $questionnaire->quizable;
            if ($course) {
                $courseSnapshot = [
                    'id' => $course->id,
                    'title' => $course->title,
                    'slug' => $course->slug,
                ];
            }
        }

        if ($questionnaire->quizable_type === CourseModule::class && $questionnaire->quizable_id) {
            $module = $questionnaire->quizable;
            if ($module) {
                $moduleSnapshot = [
                    'id' => $module->id,
                    'title' => $module->title,
                    'course_id' => $module->course_id,
                ];

                if ($module->course) {
                    $courseSnapshot = [
                        'id' => $module->course->id,
                        'title' => $module->course->title,
                        'slug' => $module->course->slug,
                    ];
                }
            }
        }

        return QuizAttempt::create([
            'tenant_id' => $context->tenant->id,
            'user_id' => $context->user->id,
            'questionnaire_id' => $questionnaire->id,
            'status' => 'in_progress',
            'questionnaire_snapshot' => $questionnaireSnapshot,
            'questions_snapshot' => $questionsSnapshot,
            'course_snapshot' => $courseSnapshot,
            'module_snapshot' => $moduleSnapshot,
            'started_at' => now(),
            'time_spent_seconds' => 0,
        ]);
    }

    /**
     * Freeze every active question (including the answer key) server-side so
     * scoring never depends on client-provided data.
     *
     * @return list<array{id: int, question: string, type: string, options: array, correct_options: array, explanation: string|null, points: int, sort_order: int}>
     */
    private function freezeQuestions(Questionnaire $questionnaire): array
    {
        return $questionnaire->questions
            ->sortBy('sort_order')
            ->values()
            ->filter(fn ($item): bool => $item->question !== null && $item->question->is_active)
            ->map(fn ($item): array => [
                'id' => $item->question->id,
                'question' => $item->question->question,
                'type' => $item->question->type,
                'options' => $item->question->options,
                'correct_options' => $item->question->correct_options,
                'explanation' => $item->question->explanation,
                'points' => $item->question->points,
                'sort_order' => $item->sort_order,
            ])
            ->values()
            ->all();
    }
}
