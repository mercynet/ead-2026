<?php

namespace App\Modules\Assessment\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Arr;

class AttemptResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'questionnaire_id' => $this->questionnaire_id,
            'status' => $this->status,
            'questionnaire_snapshot' => $this->questionnaire_snapshot,
            'questions' => collect($this->questions_snapshot ?? [])
                ->map(fn (array $question): array => Arr::except($question, ['correct_options', 'explanation']))
                ->values()
                ->all(),
            'course_snapshot' => $this->course_snapshot,
            'module_snapshot' => $this->module_snapshot,
            'score' => $this->score,
            'passed' => $this->passed,
            'started_at' => $this->started_at?->toIso8601String(),
            'finished_at' => $this->finished_at?->toIso8601String(),
            'time_spent_seconds' => $this->time_spent_seconds,
            'answers' => AttemptAnswerResource::collection($this->whenLoaded('answers')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
