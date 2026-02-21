<?php

namespace App\Http\Controllers\Api\V1\Core;

use App\Actions\Core\Users\ListUsersAction;
use App\Actions\Core\Users\RegisterUserAction;
use App\Actions\Core\Users\ShowUserAction;
use App\Actions\Core\Users\UpdatePasswordAction;
use App\Actions\Core\Users\UpdateProfileAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Core\Users\RegisterUserRequest;
use App\Http\Requests\Core\Users\UpdatePasswordRequest;
use App\Http\Requests\Core\Users\UpdateProfileRequest;
use App\Http\Resources\Core\UserListResource;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\Request;
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

    public function index(Request $request): Response
    {
        /** @var User $authenticatedUser */
        $authenticatedUser = $request->user();
        $tenant = $this->resolveTenant($request);

        Gate::authorize('core.users.list', [$tenant]);

        $paginator = $this->listUsersAction->handle($authenticatedUser, $tenant);

        return response(UserListResource::collection($paginator)->response()->getData(true));
    }

    public function store(RegisterUserRequest $request): Response
    {
        $tenant = $this->resolveTenant($request);

        $user = $this->registerUserAction->handle($tenant, $request->validated());

        return response([
            'data' => [
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                ],
            ],
            'meta' => [],
        ], 201);
    }

    public function show(Request $request, User $user): Response
    {
        /** @var User $authenticatedUser */
        $authenticatedUser = $request->user();
        $tenant = $this->resolveTenant($request);

        Gate::authorize('core.users.show', [$tenant, $user]);

        return response([
            'data' => [
                'user' => $this->showUserAction->handle($user),
            ],
            'meta' => [],
        ]);
    }

    public function updateMe(UpdateProfileRequest $request): Response
    {
        /** @var User $authenticatedUser */
        $authenticatedUser = $request->user();
        $tenant = $this->resolveTenant($request);

        Gate::authorize('core.users.update-self', [$tenant, $authenticatedUser]);
        $user = $this->updateProfileAction->handle($authenticatedUser, $request->validated());

        return response([
            'data' => [
                'user' => $this->showUserAction->handle($user),
            ],
            'meta' => [],
        ]);
    }

    public function updatePassword(UpdatePasswordRequest $request): Response
    {
        /** @var User $authenticatedUser */
        $authenticatedUser = $request->user();
        $tenant = $this->resolveTenant($request);

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
            'meta' => [],
        ]);
    }

    private function resolveTenant(Request $request): Tenant
    {
        /** @var Tenant $tenant */
        $tenant = $request->attributes->get('tenant');

        return $tenant;
    }
}
