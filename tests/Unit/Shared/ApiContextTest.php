<?php

use App\Shared\Exceptions\TenantContextRequiredException;
use App\Shared\Http\ApiContext;

it('throws with user-specific message when required user is missing', function (): void {
    $context = new ApiContext(user: null, tenant: null);

    expect(fn () => $context->requiredUser())
        ->toThrow(TenantContextRequiredException::class, 'Authenticated user is required.');
});

it('throws with tenant-specific message when required tenant is missing', function (): void {
    $context = new ApiContext(user: null, tenant: null);

    expect(fn () => $context->requiredTenant())
        ->toThrow(TenantContextRequiredException::class, 'Tenant context is required.');
});

it('defaults to the tenant message when make receives no argument', function (): void {
    expect(TenantContextRequiredException::make()->getMessage())
        ->toBe('Tenant context is required.');
});
