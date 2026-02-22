<?php

namespace App\Actions\Assessment\Questionnaire;

use App\Http\Context\ApiContext;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\Questionnaire;
use Illuminate\Http\Request;

/**
 * Create a new questionnaire.
 *
 * @apiResource App\Http\Resources\Assessment\QuestionnaireResource
 *
 * @apiResourceModel App\Models\Questionnaire
 *
 * @bodyParam title string required The questionnaire title. Example: Simulado Final
 * @bodyParam description string|null The questionnaire description.
 * @bodyParam type string required The questionnaire type (lesson|course|standalone).
 * @bodyParam quizable_id int|null The ID of the linked lesson or course.
 * @bodyParam passing_score int The minimum score to pass (0-100). Default: 70.
 * @bodyParam time_limit_minutes int|null Time limit in minutes.
 * @bodyParam is_active bool Whether the questionnaire is active. Default: true.
 * @bodyParam show_results bool Whether to show results to students. Default: true.
 */
class StoreQuestionnaireAction
{
    public function handle(Request $request, ApiContext $context): Questionnaire
    {
        $data = $request->validated();

        $data['tenant_id'] = $context->tenant->id;
        $data['instructor_id'] = $context->user->id;

        if (! empty($data['quizable_id']) && ! empty($data['quizable_type'])) {
            $quizableType = $data['quizable_type'];
            $quizableId = $data['quizable_id'];

            $modelClass = match ($quizableType) {
                'lesson' => Lesson::class,
                'course' => Course::class,
                default => null,
            };

            if ($modelClass && ! $modelClass::where('id', $quizableId)->exists()) {
                abort(422, "The {$quizableType} with ID {$quizableId} does not exist.");
            }
        }

        return Questionnaire::create($data);
    }
}
