<?php

namespace App\Modules\Assessment\Actions\Attempt;

use App\Modules\Assessment\Models\QuizAttempt;
use App\Shared\Http\ApiContext;
use Illuminate\Validation\ValidationException;

/**
 * Finish an attempt and calculate the score.
 *
 * @apiResource App\Modules\Assessment\Http\Resources\AttemptResource
 *
 * @apiResourceModel App\Modules\Assessment\Models\QuizAttempt
 */
class FinishAttemptAction
{
    public function handle(int $attemptId, ApiContext $context): QuizAttempt
    {
        $attempt = QuizAttempt::query()
            ->with(['answers'])
            ->where('user_id', $context->user->id)
            ->findOrFail($attemptId);

        if ($attempt->status !== 'in_progress') {
            throw ValidationException::withMessages([
                'attempt' => ['This attempt is already completed.'],
            ]);
        }

        $totalPoints = $attempt->answers()->sum('points_earned');
        $maxPoints = collect($attempt->questions_snapshot ?? [])
            ->sum(fn (array $question): int => (int) ($question['points'] ?? 1));

        if ($maxPoints > 0) {
            $score = (int) round(($totalPoints / $maxPoints) * 100);
        } else {
            $score = 0;
        }

        $passingScore = $attempt->questionnaire_snapshot['passing_score'] ?? 70;
        $passed = $score >= $passingScore;

        $attempt->update([
            'status' => 'completed',
            'finished_at' => now(),
            'score' => $score,
            'passed' => $passed,
            'time_spent_seconds' => $attempt->started_at->diffInSeconds(now()),
        ]);

        return $attempt->fresh(['answers']);
    }
}
