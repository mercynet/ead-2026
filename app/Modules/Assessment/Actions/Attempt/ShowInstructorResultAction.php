<?php

namespace App\Modules\Assessment\Actions\Attempt;

use App\Modules\Assessment\Models\QuizAttempt;
use App\Modules\Assessment\Services\InstructorResultScope;
use App\Shared\Http\ApiContext;

class ShowInstructorResultAction
{
    public function __construct(
        private readonly InstructorResultScope $scope,
    ) {}

    public function handle(int $id, ApiContext $context): QuizAttempt
    {
        $attempt = $this->scope->attempt($context, $id);
        $this->scope->attachAttemptNumbers(collect([$attempt]), $context);

        return $attempt;
    }
}
