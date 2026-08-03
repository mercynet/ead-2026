<?php

declare(strict_types=1);

use App\Modules\Core\Models\Tenant;
use App\Modules\Core\Models\User;
use App\Modules\Ecosystem\Models\Plugin;
use App\Modules\Ecosystem\Models\PluginActivation;
use App\Modules\Ecosystem\Models\TenantPluginConfig;
use App\Modules\Ecosystem\Models\TenantPluginConfigRevision;
use Illuminate\Support\Facades\DB;

return [
    'endpoint' => 'GET /api/v1/mzrt/tenants',

    'setup' => function (array $ctx): array {
        $suffix = (string) $ctx['tenant']->id;

        return [
            'domain' => "e2e-mzrt-{$suffix}.local",
            'email' => "e2e-mzrt-admin-{$suffix}@test.local",
            'password' => 'e2e-mzrt-password-123',
            'cashPluginPreexisted' => Plugin::query()->where('slug', 'cash')->exists(),
        ];
    },

    'cases' => [
        [
            'name' => 'developer creates tenant and cash entitlement',
            'method' => 'POST',
            'tenant' => null,
            'as' => 'developer',
            'path' => '/api/v1/mzrt/tenants',
            'body' => fn (array $ctx): array => [
                'name' => 'E2E MZRT Tenant',
                'domain' => $ctx['fixtures']['domain'],
                'description' => 'Tenant criado pelo ciclo E2E MZRT.',
                'admin' => [
                    'name' => 'E2E MZRT Admin',
                    'email' => $ctx['fixtures']['email'],
                    'password' => $ctx['fixtures']['password'],
                ],
            ],
            'expect' => ['status' => 201],
            'capture' => fn (array $ctx): array => [
                'tenantId' => data_get($ctx['response']->json(), 'data.tenant.id'),
                'adminId' => data_get($ctx['response']->json(), 'data.admin.id'),
            ],
            'db' => function (array $ctx): array {
                $tenant = Tenant::query()->find($ctx['fixtures']['tenantId']);
                $admin = User::query()->find($ctx['fixtures']['adminId']);
                $cash = Plugin::query()->where('slug', 'cash')->first();
                $activation = $cash === null ? null : PluginActivation::query()
                    ->where('tenant_id', $tenant?->id)
                    ->where('plugin_id', $cash->id)
                    ->first();
                $config = $cash === null ? null : TenantPluginConfig::query()
                    ->where('tenant_id', $tenant?->id)
                    ->where('plugin_id', $cash->id)
                    ->first();

                return [
                    'tenant created' => [true, $tenant !== null],
                    'admin belongs to created tenant' => [$tenant?->id, $admin?->tenant_id],
                    'admin role assigned' => [true, $admin?->hasRole('admin')],
                    'cash activation active' => ['active', $activation?->status],
                    'cash activation attributed to admin' => [$admin?->id, $activation?->activated_by],
                    'cash config enabled' => [true, $config?->enabled],
                ];
            },
        ],
        [
            'name' => 'developer sees target entitlements without sensitive configuration',
            'method' => 'GET',
            'tenant' => null,
            'as' => 'developer',
            'path' => fn (array $ctx): string => '/api/v1/mzrt/tenants/'.$ctx['fixtures']['tenantId'].'/entitlements',
            'expect' => [
                'status' => 200,
                'json' => [
                    'data.0.capability' => 'gateway.cash',
                    'data.0.status' => 'active',
                ],
            ],
            'db' => fn (array $ctx): array => [
                'response omits config' => [false, data_has($ctx['response']->json(), 'data.0.config')],
                'response omits credentials' => [false, data_has($ctx['response']->json(), 'data.0.credentials')],
                'response omits activation actor' => [false, data_has($ctx['response']->json(), 'data.0.activated_by')],
            ],
        ],
        [
            'name' => 'created admin logs in and token is captured',
            'method' => 'POST',
            'tenant' => null,
            'path' => '/api/v1/core/auth/login',
            'headers' => ['X-Tenant-ID' => fn (array $ctx): int => $ctx['fixtures']['tenantId']],
            'body' => fn (array $ctx): array => [
                'email' => $ctx['fixtures']['email'],
                'password' => $ctx['fixtures']['password'],
            ],
            'expect' => ['status' => 200],
            'capture' => fn (array $ctx): array => ['adminToken' => data_get($ctx['response']->json(), 'data.token')],
        ],
        [
            'name' => 'developer suspends created tenant',
            'method' => 'PATCH',
            'tenant' => null,
            'as' => 'developer',
            'path' => fn (array $ctx): string => '/api/v1/mzrt/tenants/'.$ctx['fixtures']['tenantId'].'/status',
            'body' => ['status' => 'suspended'],
            'expect' => ['status' => 200, 'json' => ['data.status' => 'suspended']],
            'db' => fn (array $ctx): array => [
                'tenant is inactive' => [false, Tenant::query()->find($ctx['fixtures']['tenantId'])?->is_active],
            ],
        ],
        [
            'name' => 'suspended tenant login is rejected',
            'method' => 'POST',
            'tenant' => null,
            'path' => '/api/v1/core/auth/login',
            'headers' => ['X-Tenant-ID' => fn (array $ctx): int => $ctx['fixtures']['tenantId']],
            'body' => fn (array $ctx): array => [
                'email' => $ctx['fixtures']['email'],
                'password' => $ctx['fixtures']['password'],
            ],
            'expect' => ['status' => 401],
        ],
        [
            'name' => 'suspended tenant token has no resolved context',
            'method' => 'GET',
            'tenant' => null,
            'path' => '/api/v1/core/auth/me',
            'headers' => [
                'X-Tenant-ID' => fn (array $ctx): int => $ctx['fixtures']['tenantId'],
                'Authorization' => fn (array $ctx): string => 'Bearer '.$ctx['fixtures']['adminToken'],
            ],
            'expect' => ['status' => 422, 'json' => ['errors.0.code' => 'tenant_not_resolved']],
        ],
        [
            'name' => 'developer reactivates created tenant',
            'method' => 'PATCH',
            'tenant' => null,
            'as' => 'developer',
            'path' => fn (array $ctx): string => '/api/v1/mzrt/tenants/'.$ctx['fixtures']['tenantId'].'/status',
            'body' => ['status' => 'active'],
            'expect' => ['status' => 200, 'json' => ['data.status' => 'active']],
            'db' => fn (array $ctx): array => [
                'tenant is active' => [true, Tenant::query()->find($ctx['fixtures']['tenantId'])?->is_active],
            ],
        ],
        [
            'name' => 'reactivated tenant login succeeds',
            'method' => 'POST',
            'tenant' => null,
            'path' => '/api/v1/core/auth/login',
            'headers' => ['X-Tenant-ID' => fn (array $ctx): int => $ctx['fixtures']['tenantId']],
            'body' => fn (array $ctx): array => [
                'email' => $ctx['fixtures']['email'],
                'password' => $ctx['fixtures']['password'],
            ],
            'expect' => ['status' => 200],
        ],
        [
            'name' => 'original token works after reactivation',
            'method' => 'GET',
            'tenant' => null,
            'path' => '/api/v1/core/auth/me',
            'headers' => [
                'X-Tenant-ID' => fn (array $ctx): int => $ctx['fixtures']['tenantId'],
                'Authorization' => fn (array $ctx): string => 'Bearer '.$ctx['fixtures']['adminToken'],
            ],
            'expect' => ['status' => 200, 'json' => ['data.id' => fn (array $ctx): int => $ctx['fixtures']['adminId']]],
        ],
    ],

    'cleanup' => function (array $ctx): void {
        $tenantId = $ctx['fixtures']['tenantId'] ?? Tenant::query()
            ->where('domain', $ctx['fixtures']['domain'] ?? '')
            ->value('id');

        if ($tenantId === null) {
            return;
        }

        $configIds = TenantPluginConfig::query()->where('tenant_id', $tenantId)->pluck('id');
        TenantPluginConfigRevision::query()->whereIn('tenant_plugin_config_id', $configIds)->delete();
        TenantPluginConfig::query()->where('tenant_id', $tenantId)->delete();
        PluginActivation::query()->where('tenant_id', $tenantId)->delete();

        $userIds = User::query()->where('tenant_id', $tenantId)->pluck('id');
        DB::table('personal_access_tokens')
            ->where('tokenable_type', User::class)
            ->whereIn('tokenable_id', $userIds)
            ->delete();
        DB::table('model_has_roles')
            ->where('model_type', User::class)
            ->whereIn('model_id', $userIds)
            ->delete();
        User::query()->where('tenant_id', $tenantId)->delete();
        Tenant::query()->whereKey($tenantId)->delete();
        DB::table('activity_log')->where('subject_type', Tenant::class)->where('subject_id', $tenantId)->delete();
        DB::table('activity_log')->where('subject_type', User::class)->whereIn('subject_id', $userIds)->delete();
        DB::table('activity_log')->where('causer_type', User::class)->whereIn('causer_id', $userIds)->delete();

        if (! ($ctx['fixtures']['cashPluginPreexisted'] ?? true)) {
            $cash = Plugin::query()->where('slug', 'cash')->first();

            if ($cash !== null
                && ! PluginActivation::query()->where('plugin_id', $cash->id)->exists()
                && ! TenantPluginConfig::query()->where('plugin_id', $cash->id)->exists()) {
                $cash->delete();
            }
        }
    },
];
