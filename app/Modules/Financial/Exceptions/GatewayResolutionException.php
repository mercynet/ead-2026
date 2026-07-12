<?php

namespace App\Modules\Financial\Exceptions;

use App\Modules\Core\Models\Tenant;
use RuntimeException;

class GatewayResolutionException extends RuntimeException
{
    public static function noActiveGateway(Tenant $tenant): self
    {
        return new self("Nenhum gateway de pagamento ativo/configurado para o tenant #{$tenant->id}.");
    }

    public static function adapterNotRegistered(string $slug): self
    {
        return new self("Gateway '{$slug}' ativo para o tenant, mas sem adaptador registrado.");
    }

    public static function invalidConfiguration(string $slug): self
    {
        return new self("Configuração do gateway '{$slug}' inválida ou incompleta.");
    }
}
