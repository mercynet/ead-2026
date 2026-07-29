<?php

namespace App\Modules\Ecosystem\Actions\Admin;

use App\Modules\Core\Models\Tenant;
use App\Modules\Ecosystem\Data\TenantPaymentGatewayData;
use App\Modules\Ecosystem\Models\Plugin;
use App\Modules\Ecosystem\Models\PluginActivation;
use App\Modules\Ecosystem\Models\TenantPluginConfig;
use App\Modules\Financial\Contracts\GatewayConfigurationRegistry;
use App\Shared\Http\ApiContext;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UpdateTenantPaymentGatewayAction
{
    public function __construct(
        private readonly BuildTenantPaymentGatewayDataAction $buildTenantPaymentGatewayDataAction,
        private readonly GatewayConfigurationRegistry $gatewayConfigurationRegistry,
    ) {}

    /**
     * @param  array{enabled: bool, configuration?: array<string, mixed>}  $data
     */
    public function handle(ApiContext $context, string $slug, array $data): TenantPaymentGatewayData
    {
        return DB::transaction(function () use ($context, $slug, $data): TenantPaymentGatewayData {
            $tenant = Tenant::query()
                ->whereKey($context->requiredTenant()->getKey())
                ->lockForUpdate()
                ->firstOrFail();
            $plugin = Plugin::query()
                ->where('slug', $slug)
                ->where('kind', 'gateway')
                ->published()
                ->first();

            if ($plugin === null) {
                throw (new ModelNotFoundException)->setModel(Plugin::class, [$slug]);
            }

            $activation = PluginActivation::query()
                ->where('tenant_id', $tenant->id)
                ->where('plugin_id', $plugin->id)
                ->active()
                ->lockForUpdate()
                ->first();

            if ($activation === null) {
                throw (new ModelNotFoundException)->setModel(PluginActivation::class, [$slug]);
            }

            $configs = TenantPluginConfig::query()
                ->where('tenant_id', $tenant->id)
                ->whereHas('plugin', fn ($query) => $query->where('kind', 'gateway'))
                ->orderBy('plugin_id')
                ->lockForUpdate()
                ->get()
                ->keyBy('plugin_id');

            /** @var TenantPluginConfig|null $config */
            $config = $configs->get($plugin->id);

            $identifier = $plugin->gatewaySlug();
            $definition = $identifier === null ? null : $this->gatewayConfigurationRegistry->definition($identifier);
            $submittedConfiguration = $data['configuration'] ?? [];

            if ($data['enabled'] && $definition === null) {
                throw ValidationException::withMessages(['enabled' => ['Gateway indisponível.']]);
            }

            if ($definition === null && array_key_exists('configuration', $data)) {
                throw ValidationException::withMessages(['configuration' => ['Gateway indisponível para configuração.']]);
            }

            if ($definition === null) {
                $configuration = $config === null ? [] : $config->credentials();
            } else {
                $fields = $definition->fields;
                $unknownKeys = array_diff(array_keys($submittedConfiguration), array_keys($fields));

                if ($unknownKeys !== []) {
                    throw ValidationException::withMessages(
                        array_fill_keys($unknownKeys, ['Campo de configuração desconhecido.'])
                    );
                }

                $currentConfiguration = $config === null ? [] : array_intersect_key($config->credentials(), $fields);
                $configuration = array_replace($currentConfiguration, $submittedConfiguration);
            }

            if ($definition !== null && ($data['enabled'] || array_key_exists('configuration', $data))) {
                $validation = $this->gatewayConfigurationRegistry->validate($identifier, $configuration);

                if (! $validation->valid) {
                    throw ValidationException::withMessages($validation->errors);
                }
            }

            $config ??= new TenantPluginConfig([
                'tenant_id' => $tenant->id,
                'plugin_id' => $plugin->id,
            ]);
            $config->fill([
                'config' => $configuration,
                'enabled' => $data['enabled'],
            ]);
            $config->save();

            if ($data['enabled']) {
                foreach ($configs as $gatewayConfig) {
                    if ($gatewayConfig->plugin_id !== $plugin->id && $gatewayConfig->enabled) {
                        $gatewayConfig->fill(['enabled' => false])->save();
                    }
                }
            }

            return $this->buildTenantPaymentGatewayDataAction->handle($plugin, $config);
        });
    }
}
