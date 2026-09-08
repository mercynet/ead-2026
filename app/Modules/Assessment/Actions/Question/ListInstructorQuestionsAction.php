<?php

namespace App\Modules\Assessment\Actions\Question;

use App\Modules\Assessment\Services\InstructorAssessmentScope;
use App\Shared\Http\ApiContext;
use Illuminate\Http\Request;
use Illuminate\Pagination\CursorPaginator;

class ListInstructorQuestionsAction
{
    public function __construct(
        private readonly InstructorAssessmentScope $scope,
    ) {}

    public function handle(Request $request, ApiContext $context): CursorPaginator
    {
        return $this->scope->questions($context)
            ->with(['categories:id,name,slug'])
            ->orderBy('id')
            ->cursorPaginate(15);
    }
}
