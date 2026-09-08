<?php

namespace App\Modules\Assessment\Actions\Questionnaire;

use App\Modules\Assessment\Services\InstructorAssessmentScope;
use App\Shared\Http\ApiContext;
use Illuminate\Http\Request;
use Illuminate\Pagination\CursorPaginator;

class ListInstructorQuestionnairesAction
{
    public function __construct(
        private readonly InstructorAssessmentScope $scope,
    ) {}

    public function handle(Request $request, ApiContext $context): CursorPaginator
    {
        return $this->scope->questionnaires($context)
            ->orderBy('id')
            ->cursorPaginate(15);
    }
}
