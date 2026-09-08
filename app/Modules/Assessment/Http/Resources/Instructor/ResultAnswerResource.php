<?php

namespace App\Modules\Assessment\Http\Resources\Instructor;

use App\Modules\Assessment\Models\QuizAttemptAnswer;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin QuizAttemptAnswer
 *
 * @property int $id
 * @property array<string, mixed> $question_snapshot
 * @property array<int, int> $selected_options
 * @property bool $is_correct
 * @property int $points_earned
 * @property \Illuminate\Support\Carbon|null $answered_at
 */
class ResultAnswerResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var QuizAttemptAnswer $answer */
        $answer = $this->resource;
        /** @var array<string, mixed> $snapshot */
        $snapshot = $answer->getAttribute('question_snapshot') ?? [];

        return [
            'id' => $answer->getAttribute('id'),
            'question_id' => $snapshot['id'] ?? null,
            'question' => $snapshot['question'] ?? null,
            'type' => $snapshot['type'] ?? null,
            'selected_options' => $answer->getAttribute('selected_options'),
            'is_correct' => $answer->getAttribute('is_correct'),
            'points_earned' => $answer->getAttribute('points_earned'),
            'feedback' => $snapshot['explanation'] ?? null,
            'answered_at' => $answer->getAttribute('answered_at')?->toIso8601String(),
        ];
    }
}
