<?php

namespace App\Modules\Financial\Policies;

use App\Modules\Core\Models\Tenant;
use App\Modules\Core\Models\User;

class OrderPolicy
{
    public function list(User $user, ?Tenant $tenant = null): bool
    {
        return $this->allows($user, $tenant, 'financial.orders.list');
    }

    public function view(User $user, ?Tenant $tenant = null): bool
    {
        return $this->allows($user, $tenant, 'financial.orders.view');
    }

    private function allows(User $user, ?Tenant $tenant, string $permission): bool
    {
        return $tenant !== null
            && $user->isStudent()
            && $user->belongsToTenant($tenant)
            && $user->getAllPermissions()->contains('name', $permission);
    }
}
