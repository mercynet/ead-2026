<?php

namespace App\Modules\Learning\Actions\Enrollment;

use App\Shared\Http\ApiContext;
use Illuminate\Http\Request;
use Illuminate\Pagination\CursorPaginator;

class ListInstructorEnrollmentsAction
{
    public function __construct(
        private readonly ListEnrollmentsAction $listEnrollmentsAction,
    ) {}

    public function handle(Request $request, ApiContext $context): CursorPaginator
    {
        return $this->listEnrollmentsAction->handle($request, $context, true);
    }
}
