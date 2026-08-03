<?php

namespace App\Shared\Contracts;

interface TenantProvisioningParticipant
{
    public function provision(int $tenantId, int $adminUserId): void;
}
