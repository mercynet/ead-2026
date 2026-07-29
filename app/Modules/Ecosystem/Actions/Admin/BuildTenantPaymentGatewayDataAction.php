<?php

namespace App\Modules\Ecosystem\Actions\Admin;

use App\Modules\Ecosystem\Data\TenantPaymentGatewayData;
use App\Modules\Ecosystem\Models\Plugin;
use App\Modules\Ecosystem\Models\TenantPluginConfig;
use App\Modules\Financial\Contracts\GatewayConfigurationRegistry;

class BuildTenantPaymentGatewayDataAction
{
    public function __construct(private readonly GatewayConfigurationRegistry $gatewayConfigurationRegistry) {}

    public function handle(Plugin $plugin, ?TenantPluginConfig $config): TenantPaymentGatewayData
    {
        $identifier = $plugin->gatewaySlug();
        $definition = $identifier === null ? null : $this->gatewayConfigurationRegistry->definition($identifier);
        $credentials = $config === null ? [] : $config->credentials();
        $fields = $definition === null ? [] : $definition->fields;
        $effectiveConfiguration = array_intersect_key($credentials, $fields);
        $safeConfiguration = [];
        $configurationSchema = [];
        $configured = $definition !== null
            && $this->gatewayConfigurationRegistry->validate($identifier, $effectiveConfiguration)->valid;

        foreach ($fields as $key => $field) {
            $valueIsConfigured = array_key_exists($key, $effectiveConfiguration)
                && $effectiveConfiguration[$key] !== null
                && $effectiveConfiguration[$key] !== '';

            if (! $field['secret'] && array_key_exists($key, $effectiveConfiguration)) {
                $safeConfiguration[$key] = $effectiveConfiguration[$key];
            }

            $schemaField = [
                'key' => $key,
                'label' => $field['label'],
                'input' => $field['input'],
                'required' => $field['required'],
                'secret' => $field['secret'],
                'configured' => $valueIsConfigured,
            ];

            if (array_key_exists('options', $field)) {
                $schemaField['options'] = $field['options'];
            }

            $configurationSchema[] = $schemaField;
        }

        return new TenantPaymentGatewayData(
            plugin: $plugin->slug,
            name: $plugin->name,
            enabled: $config === null ? false : $config->enabled,
            available: $definition !== null,
            configured: $configured,
            configuration: $safeConfiguration,
            configurationSchema: $configurationSchema,
        );
    }
}
