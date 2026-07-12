<?php

namespace App\Modules\Ecosystem\Services;

use App\Modules\Core\Models\Tenant;
use App\Modules\Ecosystem\Contracts\ActiveGateway;
use App\Modules\Ecosystem\Contracts\TenantGatewayProvider;
use App\Modules\Ecosystem\Models\PluginActivation;
use App\Modules\Ecosystem\Models\TenantPluginConfig;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Log;

class EcosystemTenantGatewayProvider implements TenantGatewayProvider
{
    public function activeFor(Tenant $tenant): ?ActiveGateway
    {
        $activePluginIds = PluginActivation::query()
            ->where('tenant_id', $tenant->id)
            ->active()
            ->pluck('plugin_id');

        if ($activePluginIds->isEmpty()) {
            return null;
        }

        $configs = TenantPluginConfig::query()
            ->where('tenant_id', $tenant->id)
            ->enabled()
            ->whereIn('plugin_id', $activePluginIds)
            ->whereHas('plugin', fn (Builder $query) => $query->where('kind', 'gateway')->whereIn('status', ['published', 'active']))
            ->with('plugin')
            ->orderByDesc('id')
            ->get();

        if ($configs->isEmpty()) {
            return null;
        }

        if ($configs->count() > 1) {
            Log::warning('Tenant has multiple active gateway plugins; using the most recent.', [
                'tenant_id' => $tenant->id,
                'count' => $configs->count(),
            ]);
        }

        $chosen = $configs->first();

        return new ActiveGateway(
            slug: (string) $chosen->plugin->gatewaySlug(),
            credentials: $chosen->credentials(),
        );
    }
}
