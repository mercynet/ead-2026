<?php

namespace App\Modules\Assessment\Actions\Question;

use App\Modules\Assessment\Services\InstructorAssessmentScope;
use App\Shared\Http\ApiContext;
use Illuminate\Validation\ValidationException;

class DeleteInstructorQuestionAction
{
    public function __construct(
        private readonly InstructorAssessmentScope $scope,
    ) {}

    public function handle(int $id, ApiContext $context): void
    {
        $question = $this->scope->question($context, $id);
        $this->scope->assertQuestionHasNoAttempts($question);

        if ($question->questionnaires()->exists()) {
            throw ValidationException::withMessages([
                'question' => ['Detach this question from every questionnaire before deleting it.'],
            ]);
        }

        $question->delete();
    }
}
