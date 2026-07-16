<?php

use App\Modules\Core\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;

/*
|--------------------------------------------------------------------------
| Unicidade de email para identidades globais (developer/landlord)
|--------------------------------------------------------------------------
| O unique composto (tenant_id, email) não barra NULLs no MySQL; a coluna
| gerada tenant_scope = COALESCE(tenant_id, 0) fecha essa brecha.
*/

it('forbids two global (tenant_id null) users from sharing an email', function (): void {
    User::factory()->developer()->create(['email' => 'dev@global.test']);

    expect(fn () => User::factory()->developer()->create(['email' => 'dev@global.test']))
        ->toThrow(UniqueConstraintViolationException::class);
});

it('still allows the same email in two different tenants', function (): void {
    $tenantA = makeTenant();
    $tenantB = makeTenant();

    User::factory()->forTenant($tenantA)->create(['email' => 'same@x.test']);

    expect(User::factory()->forTenant($tenantB)->create(['email' => 'same@x.test']))
        ->toBeInstanceOf(User::class);
});

it('still forbids a duplicate email within the same tenant', function (): void {
    $tenant = makeTenant();
    User::factory()->forTenant($tenant)->create(['email' => 'dup@x.test']);

    expect(fn () => User::factory()->forTenant($tenant)->create(['email' => 'dup@x.test']))
        ->toThrow(UniqueConstraintViolationException::class);
});
