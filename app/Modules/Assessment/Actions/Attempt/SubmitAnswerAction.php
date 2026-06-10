<?php

namespace App\Modules\Assessment\Actions\Attempt;

use App\Modules\Assessment\Models\QuizAttempt;
use App\Modules\Assessment\Models\QuizAttemptAnswer;
use App\Shared\Http\ApiContext;
use Illuminate\Http\Request;

/**
 * Submit an answer to an attempt.
 *
 * @apiResource App\Modules\Assessment\Http\Resources\AttemptAnswerResource
 *
 * @apiResourceModel App\Modules\Assessment\Models\QuizAttemptAnswer
 *
 * @bodyParam selected_options array required Array of selected option indices. Example: [0, 2]
 */
class SubmitAnswerAction
{
    public function handle(Request $request, int $attemptId, ApiContext $context): QuizAttemptAnswer
    {
        $attempt = QuizAttempt::query()
            ->where('user_id', $context->user->id)
            ->findOrFail($attemptId);

        if ($attempt->status !== 'in_progress') {
            abort(422, 'This attempt is already completed.');
        }

        $data = $request->validated();
        $selectedOptions = $data['selected_options'];

        $questionSnapshot = $data['question_snapshot'];

        $correctOptions = $questionSnapshot['correct_options'] ?? [];
        $isCorrect = count(array_diff($selectedOptions, $correctOptions)) === 0
            && count(array_diff($correctOptions, $selectedOptions)) === 0;

        $points = $isCorrect ? ($questionSnapshot['points'] ?? 1) : 0;

        $answer = QuizAttemptAnswer::create([
            'tenant_id' => $context->tenant->id,
            'quiz_attempt_id' => $attempt->id,
            'question_snapshot' => $questionSnapshot,
            'selected_options' => $selectedOptions,
            'is_correct' => $isCorrect,
            'points_earned' => $points,
            'answered_at' => now(),
        ]);

        $attempt->update([
            'time_spent_seconds' => $attempt->started_at->diffInSeconds(now()),
        ]);

        return $answer;
    }
}
