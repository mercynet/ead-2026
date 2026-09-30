<?php

declare(strict_types=1);

use App\Modules\Assessment\Models\Certificate;

return [
    'endpoint' => 'POST /api/v1/admin/certificates/{id}/revoke',

    'setup' => function (array $ctx): array {
        $certificate = Certificate::factory()->create([
            'tenant_id' => $ctx['tenant']->id,
            'user_id' => $ctx['users']['student']->id,
            'certificate_number' => 'CERT-2026-E2EREVOKE',
            'status' => 'issued',
        ]);
        $foreignCertificate = Certificate::factory()->create([
            'tenant_id' => $ctx['otherTenant']->id,
            'user_id' => $ctx['users']['otherAdmin']->id,
            'certificate_number' => 'CERT-2026-E2EFOREIGN',
            'status' => 'issued',
        ]);

        return [
            'certificateId' => $certificate->id,
            'certificateNumber' => $certificate->certificate_number,
            'foreignCertificateId' => $foreignCertificate->id,
        ];
    },

    'cases' => [
        [
            'name' => 'admin revoga certificado do próprio tenant',
            'as' => 'admin',
            'method' => 'POST',
            'path' => fn (array $ctx): string => '/api/v1/admin/certificates/'.$ctx['fixtures']['certificateId'].'/revoke',
            'expect' => ['status' => 200, 'json' => ['data.status' => 'revoked']],
            'db' => fn (array $ctx): array => [
                'certificate revoked in primary tenant' => ['revoked', Certificate::query()->find($ctx['fixtures']['certificateId'])?->status],
            ],
        ],
        [
            'name' => 'repetir revogação permanece idempotente',
            'as' => 'admin',
            'method' => 'POST',
            'path' => fn (array $ctx): string => '/api/v1/admin/certificates/'.$ctx['fixtures']['certificateId'].'/revoke',
            'expect' => ['status' => 200, 'json' => ['data.status' => 'revoked']],
        ],
        [
            'name' => 'verificação pública rejeita certificado revogado',
            'method' => 'GET',
            'path' => fn (array $ctx): string => '/api/v1/assessment/certificates/verify/'.$ctx['fixtures']['certificateNumber'],
            'expect' => ['status' => 200, 'json' => ['valid' => false]],
        ],
        [
            'name' => 'admin não revoga certificado de outro tenant',
            'as' => 'admin',
            'method' => 'POST',
            'path' => fn (array $ctx): string => '/api/v1/admin/certificates/'.$ctx['fixtures']['foreignCertificateId'].'/revoke',
            'expect' => ['status' => 404, 'json' => ['errors.0.code' => 'not_found']],
            'db' => fn (array $ctx): array => [
                'foreign certificate remains issued' => ['issued', Certificate::query()->find($ctx['fixtures']['foreignCertificateId'])?->status],
            ],
        ],
        [
            'name' => 'instructor é barrado da revogação Admin',
            'as' => 'instructor',
            'method' => 'POST',
            'path' => fn (array $ctx): string => '/api/v1/admin/certificates/'.$ctx['fixtures']['certificateId'].'/revoke',
            'expect' => ['status' => 403, 'json' => ['errors.0.code' => 'area_forbidden']],
        ],
        [
            'name' => 'sem autenticação é barrado da revogação',
            'method' => 'POST',
            'path' => fn (array $ctx): string => '/api/v1/admin/certificates/'.$ctx['fixtures']['certificateId'].'/revoke',
            'expect' => ['status' => 401, 'json' => ['errors.0.code' => 'unauthenticated']],
        ],
    ],

    'cleanup' => function (array $ctx): void {
        Certificate::query()->whereIn('id', array_filter([
            $ctx['fixtures']['certificateId'] ?? null,
            $ctx['fixtures']['foreignCertificateId'] ?? null,
        ]))->delete();
    },
];
