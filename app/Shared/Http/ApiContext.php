<?php

namespace App\Shared\Http;

use App\Modules\Core\Models\Tenant;
use App\Modules\Core\Models\User;
use App\Shared\Exceptions\TenantContextRequiredException;

final readonly class ApiContext
{
    public function __construct(
        public readonly ?User $user,
        public readonly ?Tenant $tenant,
    ) {}

    public function hasUser(): bool
    {
        return $this->user !== null;
    }

    public function hasTenant(): bool
    {
        return $this->tenant !== null;
    }

    public function requiredUser(): User
    {
        if ($this->user === null) {
            throw TenantContextRequiredException::make('Authenticated user is required.');
        }

        return $this->user;
    }

    public function requiredTenant(): Tenant
    {
        if ($this->tenant === null) {
            throw TenantContextRequiredException::make('Tenant context is required.');
        }

        return $this->tenant;
    }
}
