<?php

namespace App\Actions\Core\Users;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\CursorPaginator;

class ListUsersAction
{
    public function handle(User $authenticatedUser, ?Tenant $tenant): CursorPaginator
    {
        $usersQuery = User::query()->orderBy('id');

        if ($authenticatedUser->isDeveloper()) {
            return $usersQuery->cursorPaginate(15);
        }

        if ($tenant !== null) {
            $usersQuery
                ->where('tenant_id', $tenant->id)
                ->whereDoesntHave('roles', static function (Builder $query): void {
                    $query->where('name', 'developer');
                });
        }

        return $usersQuery->cursorPaginate(15);
    }
}
