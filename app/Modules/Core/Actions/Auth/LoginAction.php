<?php

namespace App\Modules\Core\Actions\Auth;

use App\Modules\Core\Models\Tenant;
use App\Modules\Core\Models\User;
use App\Shared\Exceptions\InvalidCredentialsException;
use App\Shared\Exceptions\TenantContextRequiredException;
use App\Shared\Http\ApiContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class LoginAction
{
    public function handle(Request $request, ApiContext $context): array
    {
        $tenant = $this->resolveTenant($context);
        $user = $this->findUser($request);

        $this->validateCredentials($request, $user);
        $this->validateTenantAccess($user, $tenant);

        $tokenName = $this->buildTokenName($request);

        return [
            'token' => $user->createToken($tokenName)->plainTextToken,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ],
        ];
    }

    private function resolveTenant(ApiContext $context): ?Tenant
    {
        return $context->tenant;
    }

    private function findUser(Request $request): User
    {
        $user = User::query()
            ->where('email', $request->string('email')->toString())
            ->first();

        if ($user === null) {
            throw InvalidCredentialsException::make();
        }

        return $user;
    }

    private function validateCredentials(Request $request, User $user): void
    {
        $password = $request->string('password')->toString();

        if (! Hash::check($password, $user->password)) {
            throw InvalidCredentialsException::make();
        }
    }

    private function validateTenantAccess(User $user, ?Tenant $tenant): void
    {
        if ($user->isDeveloper()) {
            return;
        }

        if ($tenant === null) {
            throw TenantContextRequiredException::make();
        }

        if (! $tenant->is_active) {
            throw InvalidCredentialsException::make();
        }

        if ((int) $user->tenant_id !== (int) $tenant->id) {
            throw InvalidCredentialsException::make();
        }
    }

    private function buildTokenName(Request $request): string
    {
        $userAgent = substr($request->userAgent() ?? 'unknown', 0, 100);
        $deviceType = str_contains(strtolower($userAgent), 'mobile') ? 'mobile' : 'web';

        return "auth-{$deviceType}-".now()->format('Ymd');
    }
}
