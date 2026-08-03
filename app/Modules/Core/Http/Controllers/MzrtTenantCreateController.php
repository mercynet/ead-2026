<?php

namespace App\Modules\Core\Http\Controllers;

use App\Modules\Core\Actions\Tenants\ProvisionTenantAction;
use App\Modules\Core\Http\Requests\Tenants\CreateTenantRequest;
use App\Modules\Core\Http\Resources\Tenants\TenantProvisionResource;
use App\Shared\Http\ApiContext;
use App\Shared\Http\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

/**
 * @group MZRT — Tenants
 *
 * Gestão global de tenants pela plataforma MZRT.
 */
class MzrtTenantCreateController extends Controller
{
    public function __construct(private readonly ProvisionTenantAction $provisionTenantAction) {}

    /**
     * Criar tenant
     *
     * Cria tenant ativo e primeiro administrador de forma atômica.
     *
     * @response 201 scenario="Tenant criado"
     * {"data":{"tenant":{"id":1,"name":"Escola Exemplo","domain":"escola.exemplo","database":null,"description":null,"status":"active"},"admin":{"id":1,"name":"Ana Admin","email":"ana@escola.exemplo","user_type":"admin"}}}
     * @response 403 scenario="Fora da área MZRT ou sem permissão"
     * {"data":null,"errors":[{"code":"access_denied","message":"Acesso negado."}]}
     * @response 409 scenario="Domínio já cadastrado"
     * {"data":null,"errors":[{"code":"tenant_already_exists","message":"Não foi possível criar o tenant."}]}
     * @response 422 scenario="Dados inválidos"
     * {"data":null,"errors":[{"code":"validation_error","message":"A senha do admin deve ter pelo menos 8 caracteres."}]}
     */
    public function store(CreateTenantRequest $request, ApiContext $context): JsonResponse
    {
        Gate::forUser($context->requiredUser())->authorize('core.tenants.create');

        $validated = $request->validated();
        /** @var array{name: string, email: string, password: string, cpf?: string|null} $admin */
        $admin = $validated['admin'];

        $result = $this->provisionTenantAction->handle(
            [
                'name' => $validated['name'],
                'domain' => $validated['domain'],
                'database' => $validated['database'] ?? null,
                'description' => $validated['description'] ?? null,
            ],
            [
                'name' => $admin['name'],
                'email' => $admin['email'],
                'cpf' => $admin['cpf'] ?? null,
            ],
            $admin['password'],
        );

        return TenantProvisionResource::make($result)->toResponse($request)->setStatusCode(201);
    }
}
