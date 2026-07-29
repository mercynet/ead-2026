<?php

namespace App\Modules\Ecosystem\Contracts;

use App\Modules\Core\Models\Tenant;

/**
 * Fronteira pública do Ecosystem: resolve, para um tenant, o gateway de pagamento
 * que está **habilitado como plugin** (entitlement ativo + config habilitada).
 * O Financial consome este contrato — não os models do Ecosystem (invariante 11).
 */
interface TenantGatewayProvider
{
    /**
     * O gateway ativo/configurado do tenant, ou null se nenhum.
     */
    public function activeFor(Tenant $tenant): ?ActiveGateway;

    public function activeForIdentity(Tenant $tenant, int $tenantPluginConfigId, string $configurationVersion): ?ActiveGateway;
}
