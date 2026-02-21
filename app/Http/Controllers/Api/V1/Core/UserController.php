<?php

namespace App\Http\Controllers\Api\V1\Core;

use App\Actions\Core\Users\ListUsersAction;
use App\Actions\Core\Users\RegisterUserAction;
use App\Actions\Core\Users\ShowUserAction;
use App\Actions\Core\Users\UpdatePasswordAction;
use App\Actions\Core\Users\UpdateProfileAction;
use App\Http\Context\ApiContext;
use App\Http\Controllers\Controller;
use App\Http\Requests\Core\Users\RegisterUserRequest;
use App\Http\Requests\Core\Users\UpdatePasswordRequest;
use App\Http\Requests\Core\Users\UpdateProfileRequest;
use App\Http\Resources\Core\UserListResource;
use App\Models\User;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class UserController extends Controller
{
    public function __construct(
        private readonly RegisterUserAction $registerUserAction,
        private readonly ListUsersAction $listUsersAction,
        private readonly ShowUserAction $showUserAction,
        private readonly UpdateProfileAction $updateProfileAction,
        private readonly UpdatePasswordAction $updatePasswordAction,
    ) {}

    public function index(ApiContext $context): Response
    {
        Gate::forUser($context->user)->authorize('core.users.list', [$context->tenant]);

        $paginator = $this->listUsersAction->handle($context);

        return response(UserListResource::collection($paginator)->response()->getData(true));
    }

    public function store(RegisterUserRequest $request, ApiContext $context): Response
    {
        $user = $this->registerUserAction->handle($context->requiredTenant(), $request->validated());

        return response([
            'data' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ],
        ], 201);
    }

    public function show(ApiContext $context, User $user): Response
    {
        Gate::forUser($context->user)->authorize('core.users.show', [$context->tenant, $user]);

        return response([
            'data' => $this->showUserAction->handle($user),
        ]);
    }

    public function updateMe(UpdateProfileRequest $request, ApiContext $context): Response
    {
        Gate::forUser($context->user)->authorize('core.users.update-self', [$context->tenant, $context->requiredUser()]);

        $user = $this->updateProfileAction->handle($context->requiredUser(), $request->validated());

        return response([
            'data' => $this->showUserAction->handle($user),
        ]);
    }

    public function updatePassword(UpdatePasswordRequest $request, ApiContext $context): Response
    {
        Gate::forUser($context->user)->authorize('core.users.update-self', [$context->tenant, $context->requiredUser()]);

        $this->updatePasswordAction->handle(
            $context->requiredUser(),
            $request->string('current_password')->toString(),
            $request->string('password')->toString(),
        );

        return response([
            'data' => [
                'password_updated' => true,
            ],
        ]);
    }
}
