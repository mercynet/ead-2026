<?php

namespace App\Modules\Ecosystem\Actions\Mzrt;

use App\Modules\Core\Models\Tenant;
use App\Modules\Ecosystem\Models\PluginActivation;
use Illuminate\Pagination\CursorPaginator;

class ListTenantEntitlementsAction
{
    /** @return CursorPaginator<int, PluginActivation> */
    public function handle(Tenant $tenant): CursorPaginator
    {
        return PluginActivation::query()
            ->where('tenant_id', $tenant->id)
            ->with('plugin')
            ->orderBy('id')
            ->cursorPaginate(15);
    }
}
