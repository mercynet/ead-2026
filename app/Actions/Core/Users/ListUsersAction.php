<?php

namespace App\Actions\Core\Users;

use App\Http\Context\ApiContext;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\CursorPaginator;

class ListUsersAction
{
    public function handle(ApiContext $context): CursorPaginator
    {
        $usersQuery = User::query()->orderBy('id');

        if ($context->user->isDeveloper()) {
            return $usersQuery->cursorPaginate(15);
        }

        if ($context->tenant !== null) {
            $usersQuery
                ->where('tenant_id', $context->tenant->id)
                ->whereDoesntHave('roles', static function (Builder $query): void {
                    $query->where('name', 'developer');
                });
        }

        return $usersQuery->cursorPaginate(15);
    }
}
