<?php

namespace App\Modules\Financial\Contracts;

/**
 * Schema público para configuração de um gateway.
 *
 * @param  array<string, array{label: string, input: string, required: bool, secret: bool, rules: list<string>, options?: array<string, string>}>  $fields
 */
readonly class GatewayConfigurationDefinition
{
    /**
     * @param  array<string, array{label: string, input: string, required: bool, secret: bool, rules: list<string>, options?: array<string, string>}>  $fields
     */
    public function __construct(
        public string $identifier,
        public string $label,
        public array $fields,
    ) {}
}
