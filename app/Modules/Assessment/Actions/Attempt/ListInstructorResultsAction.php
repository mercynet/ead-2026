<?php

namespace App\Modules\Assessment\Actions\Attempt;

use App\Modules\Assessment\Services\InstructorResultScope;
use App\Shared\Http\ApiContext;
use Illuminate\Http\Request;
use Illuminate\Pagination\CursorPaginator;

class ListInstructorResultsAction
{
    public function __construct(
        private readonly InstructorResultScope $scope,
    ) {}

    public function handle(Request $request, ApiContext $context): CursorPaginator
    {
        $paginator = $this->scope->attempts($context)->cursorPaginate(15);
        $this->scope->attachAttemptNumbers($paginator->getCollection(), $context);

        return $paginator;
    }
}
