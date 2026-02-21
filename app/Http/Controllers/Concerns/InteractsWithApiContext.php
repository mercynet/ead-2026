<?php

namespace App\Http\Controllers\Concerns;

use App\Exceptions\TenantContextRequiredException;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\Request;

trait InteractsWithApiContext
{
    protected function authenticatedUser(?Request $request = null): ?User
    {
        $request ??= request();

        /** @var User|null $user */
        $user = $request->user('sanctum') ?? $request->user();

        return $user;
    }

    protected function currentTenant(): ?Tenant
    {
        if (! app()->bound('tenant')) {
            return null;
        }

        $tenant = app('tenant');

        return $tenant instanceof Tenant ? $tenant : null;
    }

    protected function requiredTenant(): Tenant
    {
        $tenant = $this->currentTenant();
        if ($tenant === null) {
            throw TenantContextRequiredException::make();
        }

        return $tenant;
    }
}
