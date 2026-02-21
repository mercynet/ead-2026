<?php

namespace App\Http\Context;

use App\Models\Tenant;
use App\Models\User;

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
            throw new \RuntimeException('Authenticated user is required.');
        }

        return $this->user;
    }

    public function requiredTenant(): Tenant
    {
        if ($this->tenant === null) {
            throw new \RuntimeException('Tenant context is required.');
        }

        return $this->tenant;
    }
}
