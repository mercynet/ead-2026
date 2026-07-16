<?php

namespace App\Modules\Core\Http\Controllers;

use App\Modules\Core\Actions\Invitations\AcceptInvitationAction;
use App\Modules\Core\Actions\Invitations\CreateInvitationAction;
use App\Modules\Core\Http\Requests\Invitations\AcceptInvitationRequest;
use App\Modules\Core\Http\Requests\Invitations\StoreInvitationRequest;
use App\Modules\Core\Http\Resources\Invitations\InvitationAcceptanceResource;
use App\Modules\Core\Http\Resources\Invitations\InvitationResource;
use App\Shared\Http\ApiContext;
use App\Shared\Http\Controller;
use Illuminate\Support\Facades\Gate;

/**
 * @group Convites
 *
 * Onboarding tenant-bound: admin convida membros; o convidado aceita com o token.
 */
class InvitationController extends Controller
{
    public function __construct(
        private readonly CreateInvitationAction $createInvitationAction,
        private readonly AcceptInvitationAction $acceptInvitationAction,
    ) {}

    /**
     * Criar Convite
     *
     * Emite um convite tenant-bound para `student` ou `instructor`. Retorna o
     * token opaco uma única vez (entregue ao convidado via link).
     */
    public function store(StoreInvitationRequest $request, ApiContext $context): InvitationResource
    {
        Gate::forUser($context->requiredUser())->authorize('core.invitations.create', [$context->requiredTenant()]);

        $result = $this->createInvitationAction->handle(
            $context->requiredTenant(),
            $context->requiredUser(),
            $request->validated(),
        );

        $resource = InvitationResource::make($result['invitation']);
        $resource->plainToken = $result['token'];

        return $resource;
    }

    /**
     * Aceitar Convite
     *
     * Cria o usuário com o tenant, email e papel fixados no convite. Token
     * inexistente, adulterado, expirado ou já usado falha genericamente.
     *
     * @unauthenticated
     */
    public function accept(AcceptInvitationRequest $request, ApiContext $context): InvitationAcceptanceResource
    {
        $user = $this->acceptInvitationAction->handle($request->validated());

        return InvitationAcceptanceResource::make($user);
    }
}
