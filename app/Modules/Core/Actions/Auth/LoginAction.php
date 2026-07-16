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
        $user = $this->findUser($request, $tenant);

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

    private function findUser(Request $request, ?Tenant $tenant): User
    {
        $email = $request->string('email')->toString();

        // Identidade tenant-scoped: primeiro o usuário DENTRO do tenant resolvido;
        // se não houver, cai para o developer global (tenant_id null), que autentica
        // em qualquer contexto. Sem contexto de tenant, só o developer é localizável.
        $user = null;

        if ($tenant !== null) {
            $user = User::query()
                ->where('tenant_id', $tenant->id)
                ->where('email', $email)
                ->first();
        }

        if ($user === null) {
            $user = User::query()
                ->whereNull('tenant_id')
                ->where('email', $email)
                ->first();
        }

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
