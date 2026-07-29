<?php

namespace App\Modules\Ecosystem\Data;

/**
 * @phpstan-type GatewayConfigurationField array{key: string, label: string, input: string, required: bool, secret: bool, configured: bool, options?: array<string, string>}
 */
readonly class TenantPaymentGatewayData
{
    /**
     * @param  array<string, mixed>  $configuration
     * @param  list<GatewayConfigurationField>  $configurationSchema
     */
    public function __construct(
        public string $plugin,
        public string $name,
        public bool $enabled,
        public bool $available,
        public bool $configured,
        public array $configuration,
        public array $configurationSchema,
    ) {}
}
