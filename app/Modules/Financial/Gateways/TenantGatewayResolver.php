<?php

namespace App\Modules\Financial\Gateways;

use App\Modules\Core\Models\Tenant;
use App\Modules\Ecosystem\Contracts\TenantGatewayProvider;
use App\Modules\Financial\Exceptions\GatewayResolutionException;
use App\Modules\Financial\Gateways\Data\ResolvedGateway;

/**
 * Resolve o gateway ativo de um tenant como uma unidade atômica (adaptador +
 * credenciais). A disponibilidade (entitlement + config) vem do Ecosystem via
 * contrato; o adaptador vem do registro do Financial. Valida a config na
 * resolução para config incompleta não chegar à cobrança.
 */
class TenantGatewayResolver
{
    public function __construct(
        private readonly PaymentGatewayManager $manager,
        private readonly TenantGatewayProvider $provider,
    ) {}

    public function resolve(Tenant $tenant): ResolvedGateway
    {
        $active = $this->provider->activeFor($tenant);

        if ($active === null) {
            throw GatewayResolutionException::noActiveGateway($tenant);
        }

        $adapter = $this->manager->get($active->slug);

        if ($adapter === null) {
            throw GatewayResolutionException::adapterNotRegistered($active->slug);
        }

        if (! $adapter->validateConfiguration($active->credentials)) {
            throw GatewayResolutionException::invalidConfiguration($active->slug);
        }

        return new ResolvedGateway($adapter, $active->credentials, $active->tenantPluginConfigId, $active->configurationVersion);
    }

    public function resolveExact(Tenant $tenant, int $tenantPluginConfigId, string $configurationVersion, string $expectedSlug): ResolvedGateway
    {
        $active = $this->provider->activeForIdentity($tenant, $tenantPluginConfigId, $configurationVersion);
        if ($active === null) {
            throw GatewayResolutionException::noActiveGateway($tenant);
        }
        if ($active->slug !== $expectedSlug) {
            throw GatewayResolutionException::noActiveGateway($tenant);
        }
        $adapter = $this->manager->get($active->slug);
        if ($adapter === null || ! $adapter->validateConfiguration($active->credentials)) {
            throw GatewayResolutionException::invalidConfiguration($active->slug);
        }

        return new ResolvedGateway($adapter, $active->credentials, $active->tenantPluginConfigId, $active->configurationVersion);
    }

    public function hasActiveFor(Tenant $tenant): bool
    {
        return $this->provider->activeFor($tenant) !== null;
    }
}
