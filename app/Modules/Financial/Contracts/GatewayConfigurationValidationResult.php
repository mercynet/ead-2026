<?php

namespace App\Modules\Financial\Contracts;

/**
 * Resultado seguro da validação de configuração de gateway.
 *
 * @param  array<string, list<string>>  $errors
 */
readonly class GatewayConfigurationValidationResult
{
    /**
     * @param  array<string, list<string>>  $errors
     */
    public function __construct(
        public bool $valid,
        public array $errors = [],
    ) {}
}
