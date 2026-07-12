<?php

namespace App\Modules\Financial\Gateways;

use App\Modules\Financial\Exceptions\GatewayResolutionException;
use App\Modules\Financial\Gateways\Data\ResolvedGateway;
use App\Modules\Financial\Models\PlatformPaymentGateway;

/**
 * Resolve o gateway ativo das vendas da plataforma (Mzrt→tenant) como unidade
 * atômica (adaptador + credenciais). Escopo global (landlord) — não passa pelo
 * Ecosystem/entitlement (o Mzrt não "compra" o próprio gateway).
 */
class PlatformGatewayResolver
{
    public function __construct(
        private readonly PaymentGatewayManager $manager,
    ) {}

    public function resolve(): ResolvedGateway
    {
        $config = PlatformPaymentGateway::query()
            ->active()
            ->orderByDesc('is_default')
            ->orderBy('id')
            ->first();

        if ($config === null) {
            throw GatewayResolutionException::noActivePlatformGateway();
        }

        $adapter = $this->manager->get($config->gateway_slug);

        if ($adapter === null) {
            throw GatewayResolutionException::adapterNotRegistered($config->gateway_slug);
        }

        if (! $adapter->validateConfiguration($config->credentials())) {
            throw GatewayResolutionException::invalidConfiguration($config->gateway_slug);
        }

        return new ResolvedGateway($adapter, $config->credentials());
    }

    public function hasActive(): bool
    {
        return PlatformPaymentGateway::query()->active()->exists();
    }
}
