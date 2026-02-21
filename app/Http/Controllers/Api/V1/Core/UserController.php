<?php

namespace App\Http\Controllers\Api\V1\Core;

use App\Actions\Core\Users\ListUsersAction;
use App\Actions\Core\Users\RegisterUserAction;
use App\Actions\Core\Users\ShowUserAction;
use App\Actions\Core\Users\UpdatePasswordAction;
use App\Actions\Core\Users\UpdateProfileAction;
use App\Http\Controllers\Concerns\InteractsWithApiContext;
use App\Http\Controllers\Controller;
use App\Http\Requests\Core\Users\RegisterUserRequest;
use App\Http\Requests\Core\Users\UpdatePasswordRequest;
use App\Http\Requests\Core\Users\UpdateProfileRequest;
use App\Http\Resources\Core\UserListResource;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class UserController extends Controller
{
    use InteractsWithApiContext;

    public function __construct(
        private readonly RegisterUserAction $registerUserAction,
        private readonly ListUsersAction $listUsersAction,
        private readonly ShowUserAction $showUserAction,
        private readonly UpdateProfileAction $updateProfileAction,
        private readonly UpdatePasswordAction $updatePasswordAction,
    ) {}

    public function index(Request $request): Response
    {
        $authenticatedUser = $this->authenticatedUser($request);
        $tenant = $this->currentTenant();

        Gate::authorize('core.users.list', [$tenant]);

        $paginator = $this->listUsersAction->handle($authenticatedUser, $tenant);

        return response(UserListResource::collection($paginator)->response()->getData(true));
    }

    public function store(RegisterUserRequest $request): Response
    {
        $tenant = $this->requiredTenant();

        $user = $this->registerUserAction->handle($tenant, $request->validated());

        return response([
            'data' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ],
        ], 201);
    }

    public function show(Request $request, User $user): Response
    {
        $authenticatedUser = $this->authenticatedUser($request);
        $tenant = $this->currentTenant();

        Gate::authorize('core.users.show', [$tenant, $user]);

        return response([
            'data' => $this->showUserAction->handle($user),
        ]);
    }

    public function updateMe(UpdateProfileRequest $request): Response
    {
        $authenticatedUser = $this->authenticatedUser($request);
        $tenant = $this->currentTenant();

        Gate::authorize('core.users.update-self', [$tenant, $authenticatedUser]);
        $user = $this->updateProfileAction->handle($authenticatedUser, $request->validated());

        return response([
            'data' => $this->showUserAction->handle($user),
        ]);
    }

    public function updatePassword(UpdatePasswordRequest $request): Response
    {
        $authenticatedUser = $this->authenticatedUser($request);
        $tenant = $this->currentTenant();

        Gate::authorize('core.users.update-self', [$tenant, $authenticatedUser]);

        $this->updatePasswordAction->handle(
            $authenticatedUser,
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
