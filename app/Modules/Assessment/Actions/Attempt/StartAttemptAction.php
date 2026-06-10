<?php

namespace App\Modules\Assessment\Actions\Attempt;

use App\Modules\Assessment\Models\Questionnaire;
use App\Modules\Assessment\Models\QuizAttempt;
use App\Modules\Learning\Models\Course;
use App\Modules\Learning\Models\CourseModule;
use App\Shared\Http\ApiContext;

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
            ->with(['instructor'])
            ->where('tenant_id', $context->tenant->id)
            ->findOrFail($questionnaireId);

        if (! $questionnaire->is_active) {
            abort(422, 'This questionnaire is not active.');
        }

        $hasInProgressAttempt = $context->user->attempts()
            ->where('questionnaire_id', $questionnaireId)
            ->where('status', 'in_progress')
            ->exists();

        if ($hasInProgressAttempt) {
            abort(422, 'You already have an in-progress attempt for this questionnaire.');
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
            'course_snapshot' => $courseSnapshot,
            'module_snapshot' => $moduleSnapshot,
            'started_at' => now(),
            'time_spent_seconds' => 0,
        ]);
    }
}
