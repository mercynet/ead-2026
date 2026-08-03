<?php

namespace App\Modules\Core\Actions\Tenants;

use App\Modules\Core\Models\Tenant;

class UpdateTenantStatusAction
{
    public function handle(Tenant $tenant, string $status): Tenant
    {
        $tenant->fill([
            'is_active' => $status === 'active',
        ]);

        if ($tenant->isDirty()) {
            $tenant->save();
        }

        return $tenant;
    }
}
