<?php

namespace App\Http\Resources\Assessment;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AttemptAnswerResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'quiz_attempt_id' => $this->quiz_attempt_id,
            'question_snapshot' => $this->question_snapshot,
            'selected_options' => $this->selected_options,
            'is_correct' => $this->is_correct,
            'points_earned' => $this->points_earned,
            'answered_at' => $this->answered_at?->toIso8601String(),
        ];
    }
}
