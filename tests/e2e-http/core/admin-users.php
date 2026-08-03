<?php

declare(strict_types=1);

use App\Modules\Core\Enums\UserType;
use App\Modules\Core\Models\User;
use Laravel\Sanctum\PersonalAccessToken;

return [
    'endpoint' => 'PATCH /api/v1/admin/users/{user}',

    'setup' => function (array $ctx): array {
        $student = User::query()->create([
            'tenant_id' => $ctx['tenant']->id,
            'user_type' => UserType::Student,
            'name' => 'Aluno Admin Users E2E',
            'email' => 'aluno-admin-users-e2e@tenant.local',
            'password' => bcrypt('password123'),
        ]);

        $peerAdmin = User::query()->create([
            'tenant_id' => $ctx['tenant']->id,
            'user_type' => UserType::Admin,
            'name' => 'Admin Par Admin Users E2E',
            'email' => 'admin-par-admin-users-e2e@tenant.local',
            'password' => bcrypt('password123'),
        ]);

        $otherTenantStudent = User::query()->create([
            'tenant_id' => $ctx['otherTenant']->id,
            'user_type' => UserType::Student,
            'name' => 'Aluno Outro Tenant E2E',
            'email' => 'aluno-outro-tenant-e2e@tenant.local',
            'password' => bcrypt('password123'),
        ]);

        $studentToken = $student->createToken('aluno-admin-users-e2e')->plainTextToken;

        return compact('student', 'peerAdmin', 'otherTenantStudent', 'studentToken');
    },

    'cases' => [
        [
            'name' => 'admin atualiza perfil de student do tenant',
            'as' => 'admin',
            'path' => fn (array $ctx): string => '/api/v1/admin/users/'.$ctx['fixtures']['student']->id,
            'body' => ['name' => 'Aluno Renomeado E2E', 'headline' => 'Aluno dedicado'],
            'expect' => ['status' => 200, 'json' => ['data.name' => 'Aluno Renomeado E2E']],
            'db' => fn (array $ctx): array => [
                'nome persistido' => ['Aluno Renomeado E2E', $ctx['fixtures']['student']->fresh()->name],
                'headline persistida' => ['Aluno dedicado', $ctx['fixtures']['student']->fresh()->headline],
                'user_type intacto' => [UserType::Student, $ctx['fixtures']['student']->fresh()->user_type],
            ],
        ],
        [
            'name' => 'user_type no payload → 422',
            'as' => 'admin',
            'path' => fn (array $ctx): string => '/api/v1/admin/users/'.$ctx['fixtures']['student']->id,
            'body' => ['user_type' => 'admin'],
            'expect' => ['status' => 422, 'json' => ['errors.0.code' => 'validation_error']],
            'db' => fn (array $ctx): array => [
                'segue student' => [UserType::Student, $ctx['fixtures']['student']->fresh()->user_type],
            ],
        ],
        [
            'name' => 'admin não administra outro admin → 403',
            'as' => 'admin',
            'path' => fn (array $ctx): string => '/api/v1/admin/users/'.$ctx['fixtures']['peerAdmin']->id,
            'body' => ['name' => 'Escalada Lateral E2E'],
            'expect' => ['status' => 403, 'json' => ['errors.0.code' => 'access_denied']],
            'db' => fn (array $ctx): array => [
                'nome preservado' => ['Admin Par Admin Users E2E', $ctx['fixtures']['peerAdmin']->fresh()->name],
            ],
        ],
        [
            'name' => 'usuário de outro tenant → 404',
            'as' => 'admin',
            'path' => fn (array $ctx): string => '/api/v1/admin/users/'.$ctx['fixtures']['otherTenantStudent']->id,
            'body' => ['name' => 'Cross Tenant E2E'],
            'expect' => ['status' => 404, 'json' => ['errors.0.code' => 'not_found']],
            'db' => fn (array $ctx): array => [
                'nome preservado' => ['Aluno Outro Tenant E2E', $ctx['fixtures']['otherTenantStudent']->fresh()->name],
            ],
        ],
        [
            'name' => 'developer é barrado pela guarda de área → 403',
            'as' => 'developer',
            'path' => fn (array $ctx): string => '/api/v1/admin/users/'.$ctx['fixtures']['student']->id,
            'body' => ['name' => 'Developer E2E'],
            'expect' => ['status' => 403, 'json' => ['errors.0.code' => 'area_forbidden']],
        ],
        [
            'name' => 'token do student ainda vale antes da exclusão',
            'method' => 'GET',
            'path' => '/api/v1/core/auth/me',
            'headers' => fn (array $ctx): array => [
                'Authorization' => 'Bearer '.$ctx['fixtures']['studentToken'],
            ],
            'expect' => ['status' => 200],
        ],
        [
            'name' => 'admin exclui student do tenant',
            'as' => 'admin',
            'method' => 'DELETE',
            'path' => fn (array $ctx): string => '/api/v1/admin/users/'.$ctx['fixtures']['student']->id,
            'expect' => ['status' => 200, 'json' => ['message' => 'User deleted successfully.']],
            'db' => function (array $ctx): array {
                $student = $ctx['fixtures']['student'];

                return [
                    'sumiu das consultas' => [null, User::query()->find($student->id)],
                    'soft delete gravado' => [true, User::withTrashed()->find($student->id)?->deleted_at !== null],
                    'sessões revogadas' => [0, PersonalAccessToken::query()->where('tokenable_id', $student->id)->count()],
                ];
            },
        ],
        [
            'name' => 'token do student excluído deixa de autenticar → 401',
            'method' => 'GET',
            'path' => '/api/v1/core/auth/me',
            'headers' => fn (array $ctx): array => [
                'Authorization' => 'Bearer '.$ctx['fixtures']['studentToken'],
            ],
            'expect' => ['status' => 401],
        ],
        [
            'name' => 'login do student excluído → 401',
            'method' => 'POST',
            'path' => '/api/v1/core/auth/login',
            'body' => ['email' => 'aluno-admin-users-e2e@tenant.local', 'password' => 'password123'],
            'expect' => ['status' => 401, 'json' => ['errors.0.code' => 'invalid_credentials']],
        ],
        [
            'name' => 'student já excluído → 404',
            'as' => 'admin',
            'method' => 'DELETE',
            'path' => fn (array $ctx): string => '/api/v1/admin/users/'.$ctx['fixtures']['student']->id,
            'expect' => ['status' => 404, 'json' => ['errors.0.code' => 'not_found']],
        ],
        [
            'name' => 'sem auth → 401',
            'path' => fn (array $ctx): string => '/api/v1/admin/users/'.$ctx['fixtures']['peerAdmin']->id,
            'body' => ['name' => 'Sem Auth E2E'],
            'expect' => ['status' => 401],
        ],
    ],

    'cleanup' => function (array $ctx): void {
        $emails = [
            'aluno-admin-users-e2e@tenant.local',
            'admin-par-admin-users-e2e@tenant.local',
            'aluno-outro-tenant-e2e@tenant.local',
        ];

        $userIds = User::withTrashed()->whereIn('email', $emails)->pluck('id');

        PersonalAccessToken::query()
            ->where('tokenable_type', User::class)
            ->whereIn('tokenable_id', $userIds)
            ->delete();

        User::withTrashed()->whereIn('id', $userIds)->forceDelete();
    },
];
