<?php

namespace App\Modules\Ecosystem\Actions\Admin;

use App\Modules\Ecosystem\Data\TenantPaymentGatewayData;
use App\Modules\Ecosystem\Models\PluginActivation;
use App\Modules\Ecosystem\Models\TenantPluginConfig;
use App\Shared\Http\ApiContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\CursorPaginator;
use Illuminate\Support\Collection;

class ListTenantPaymentGatewaysAction
{
    public function __construct(private readonly BuildTenantPaymentGatewayDataAction $buildTenantPaymentGatewayDataAction) {}

    /** @return CursorPaginator<int, TenantPaymentGatewayData> */
    public function handle(ApiContext $context): CursorPaginator
    {
        $tenant = $context->requiredTenant();
        /** @var CursorPaginator<int, PluginActivation> $paginator */
        $paginator = PluginActivation::query()
            ->where('tenant_id', $tenant->id)
            ->active()
            ->whereHas('plugin', function (Builder $query): void {
                $query->where('kind', 'gateway')->whereIn('status', ['published', 'active']);
            })
            ->with('plugin')
            ->orderBy('plugin_id')
            ->cursorPaginate(15);

        /** @var Collection<int, TenantPluginConfig> $configs */
        $configs = TenantPluginConfig::query()
            ->where('tenant_id', $tenant->id)
            ->whereIn('plugin_id', $paginator->getCollection()->pluck('plugin_id'))
            ->get()
            ->keyBy('plugin_id');

        return $paginator->through(
            fn (PluginActivation $activation): TenantPaymentGatewayData => $this->buildTenantPaymentGatewayDataAction->handle(
                $activation->plugin,
                $configs->get($activation->plugin_id),
            )
        );
    }
}
