<?php

namespace App\Modules\Ecosystem\Services;

use App\Modules\Ecosystem\Contracts\DefaultGatewayProvisioner;
use App\Modules\Ecosystem\Models\Plugin;
use App\Modules\Ecosystem\Models\PluginActivation;
use App\Modules\Ecosystem\Models\TenantPluginConfig;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class EcosystemDefaultGatewayProvisioner implements DefaultGatewayProvisioner
{
    public function ensureForTenant(int $tenantId, int $activatedByUserId): void
    {
        DB::transaction(function () use ($tenantId, $activatedByUserId): void {
            $cashPlugin = Plugin::query()->firstOrCreate(
                ['slug' => 'cash'],
                [
                    'name' => 'Dinheiro',
                    'capability_key' => 'gateway.cash',
                    'kind' => 'gateway',
                    'status' => 'published',
                    'visibility' => 'public',
                    'tier' => 'free',
                    'is_curated' => true,
                    'short_description' => 'Recebimento em dinheiro com confirmação manual.',
                    'long_description' => 'Gateway manual para pagamentos em dinheiro; o administrador confirma o pagamento antes da matrícula.',
                ],
            );

            PluginActivation::query()->firstOrCreate(
                [
                    'tenant_id' => $tenantId,
                    'plugin_id' => $cashPlugin->id,
                ],
                [
                    'status' => 'active',
                    'activated_at' => now(),
                    'activated_by' => $activatedByUserId,
                ],
            );

            TenantPluginConfig::query()->firstOrCreate(
                [
                    'tenant_id' => $tenantId,
                    'plugin_id' => $cashPlugin->id,
                ],
                [
                    'config' => [],
                    'enabled' => true,
                    'configuration_version' => (string) Str::uuid(),
                ],
            );
        });
    }
}
