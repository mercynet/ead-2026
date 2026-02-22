<?php

namespace App\Actions\Assessment\Attempt;

use App\Http\Context\ApiContext;
use App\Models\QuizAttempt;

/**
 * Finish an attempt and calculate the score.
 *
 * @apiResource App\Http\Resources\Assessment\AttemptResource
 *
 * @apiResourceModel App\Models\QuizAttempt
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
            abort(422, 'This attempt is already completed.');
        }

        $totalPoints = $attempt->answers()->sum('points_earned');
        $maxPoints = collect($attempt->questionnaire_snapshot['points'] ?? [])
            ->sum() ?? $attempt->answers()->count();

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
