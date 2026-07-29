<?php

namespace App\Modules\Financial\Contracts;

interface GatewayConfigurationRegistry
{
    public function definition(string $identifier): ?GatewayConfigurationDefinition;

    /**
     * @param  array<string, mixed>  $configuration
     */
    public function validate(string $identifier, array $configuration): GatewayConfigurationValidationResult;
}
