<?php

return [
    'endpoint' => 'GET /initial',
    'cases' => [
        [
            'name' => 'POST without automatic tenant header captures values',
            'method' => 'POST',
            'tenant' => null,
            'path' => '/created',
            'expect' => ['status' => 201],
            'capture' => fn (array $ctx): array => [
                'tenantId' => data_get($ctx['response']->json(), 'data.tenant.id'),
                'adminToken' => data_get($ctx['response']->json(), 'data.token'),
            ],
        ],
        [
            'name' => 'PATCH uses captured tenant value',
            'method' => 'PATCH',
            'path' => fn (array $ctx): string => '/later/'.$ctx['fixtures']['tenantId'],
            'headers' => [
                'X-Captured-Tenant-ID' => fn (array $ctx): int => $ctx['fixtures']['tenantId'],
            ],
            'expect' => [
                'status' => 200,
                'json' => ['data.tenant_id' => fn (array $ctx): int => $ctx['fixtures']['tenantId']],
            ],
        ],
    ],
];
