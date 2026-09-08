<?php

namespace App\Modules\Assessment\Http\Resources\Instructor;

use App\Modules\Assessment\Models\Questionnaire;
use App\Modules\Assessment\Models\QuizAttempt;
use App\Modules\Core\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin QuizAttempt
 *
 * @property int $id
 * @property int $questionnaire_id
 * @property int|null $score
 * @property bool|null $passed
 * @property \Illuminate\Support\Carbon|null $started_at
 * @property \Illuminate\Support\Carbon|null $finished_at
 * @property int $time_spent_seconds
 * @property int $attempt_number
 * @property-read Questionnaire|null $questionnaire
 * @property-read User|null $user
 */
class ResultResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var QuizAttempt $attempt */
        $attempt = $this->resource;
        /** @var Questionnaire|null $questionnaire */
        $questionnaire = $attempt->questionnaire;
        /** @var User|null $student */
        $student = $attempt->user;

        return [
            'attempt_id' => $attempt->getAttribute('id'),
            'questionnaire_id' => $attempt->getAttribute('questionnaire_id'),
            'questionnaire' => [
                'id' => $questionnaire?->getAttribute('id'),
                'title' => $questionnaire?->getAttribute('title'),
                'type' => $questionnaire?->getAttribute('type'),
            ],
            'student' => [
                'id' => $student?->getAttribute('id'),
                'name' => $student?->getAttribute('name'),
                'avatar' => $student?->getAttribute('avatar'),
            ],
            'score' => $attempt->getAttribute('score'),
            'percentage' => $attempt->getAttribute('score'),
            'passed' => $attempt->getAttribute('passed'),
            'started_at' => $attempt->getAttribute('started_at')?->toIso8601String(),
            'completed_at' => $attempt->getAttribute('finished_at')?->toIso8601String(),
            'time_spent_seconds' => $attempt->getAttribute('time_spent_seconds'),
            'attempt_number' => $attempt->getAttribute('attempt_number'),
            'answers' => ResultAnswerResource::collection($this->whenLoaded('answers')),
        ];
    }
}
