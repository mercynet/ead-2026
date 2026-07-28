<?php

namespace App\Modules\Ecosystem\Contracts;

/**
 * Fronteira pública para provisionar o gateway padrão de um tenant novo.
 */
interface DefaultGatewayProvisioner
{
    public function ensureForTenant(int $tenantId, int $activatedByUserId): void;
}
