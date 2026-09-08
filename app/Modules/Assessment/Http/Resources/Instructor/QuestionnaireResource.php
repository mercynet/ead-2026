<?php

namespace App\Modules\Assessment\Http\Resources\Instructor;

use App\Modules\Assessment\Models\Questionnaire;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Questionnaire
 *
 * @property int $id
 * @property string $title
 * @property string|null $description
 * @property string $type
 * @property int|null $quizable_id
 * @property string|null $quizable_type
 * @property int $passing_score
 * @property int|null $time_limit_minutes
 * @property bool $is_active
 * @property bool $show_results
 * @property int|null $instructor_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class QuestionnaireResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var Questionnaire $questionnaire */
        $questionnaire = $this->resource;

        return [
            'id' => $questionnaire->getAttribute('id'),
            'title' => $questionnaire->getAttribute('title'),
            'description' => $questionnaire->getAttribute('description'),
            'type' => $questionnaire->getAttribute('type'),
            'quizable_id' => $questionnaire->getAttribute('quizable_id'),
            'quizable_type' => $questionnaire->getAttribute('quizable_type'),
            'passing_score' => $questionnaire->getAttribute('passing_score'),
            'time_limit_minutes' => $questionnaire->getAttribute('time_limit_minutes'),
            'is_active' => $questionnaire->getAttribute('is_active'),
            'show_results' => $questionnaire->getAttribute('show_results'),
            'instructor_id' => $questionnaire->getAttribute('instructor_id'),
            'questions' => QuestionnaireQuestionResource::collection($this->whenLoaded('questions')),
            'created_at' => $questionnaire->getAttribute('created_at')?->toIso8601String(),
            'updated_at' => $questionnaire->getAttribute('updated_at')?->toIso8601String(),
        ];
    }
}
