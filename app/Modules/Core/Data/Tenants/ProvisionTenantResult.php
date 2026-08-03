<?php

namespace App\Modules\Core\Data\Tenants;

use App\Modules\Core\Models\Tenant;
use App\Modules\Core\Models\User;

final readonly class ProvisionTenantResult
{
    public function __construct(
        public Tenant $tenant,
        public User $admin,
        public bool $tenantCreated,
        public bool $adminCreated,
        public bool $adminPromoted,
    ) {}
}
