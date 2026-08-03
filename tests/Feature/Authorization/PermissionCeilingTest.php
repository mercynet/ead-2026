<?php

use App\Modules\Core\Enums\UserType;
use App\Modules\Core\Models\User;
use Illuminate\Support\Facades\Gate;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

it('denies canonical permissions granted through an ineligible role', function (UserType $userType, string $excessPermission): void {
    [$user, $headers] = actingAsUserType($userType);
    $role = Role::query()->create([
        'name' => "permission-ceiling-{$userType->value}-{$user->id}",
        'guard_name' => 'web',
        'scope' => 'tenant',
        'tenant_id' => $user->tenant_id,
    ]);
    $role->syncPermissions([$excessPermission]);

    expect($role->hasPermissionTo($excessPermission))->toBeTrue();

    $user->assignRole($role);

    expect($user->hasPermissionTo($excessPermission))->toBeFalse()
        ->and($user->checkPermissionTo($excessPermission))->toBeFalse()
        ->and($user->can($excessPermission))->toBeFalse()
        ->and($user->getAllPermissions()->pluck('name'))->not->toContain($excessPermission);

    $response = $this->getJson('/api/v1/core/auth/me', $headers)->assertSuccessful();

    expect(collect($response->json('data.permissions'))->pluck('name'))->not->toContain($excessPermission);
})->with([
    'admin cannot receive student-only permission' => [UserType::Admin, 'learning.progress.update'],
    'instructor cannot receive admin-only permission' => [UserType::Instructor, 'financial.payment-gateways.update'],
    'student cannot receive instructor-only permission' => [UserType::Student, 'learning.courses.create'],
]);

it('fails closed for noncanonical stored permissions', function (): void {
    [$user] = actingAsUserType(UserType::Admin);
    $permission = Permission::query()->create([
        'name' => 'legacy.permissions.unregistered',
        'guard_name' => 'web',
    ]);
    $user->givePermissionTo($permission);

    expect($user->hasPermissionTo($permission))->toBeFalse()
        ->and($user->checkPermissionTo($permission))->toBeFalse()
        ->and($user->can($permission->name))->toBeFalse()
        ->and($user->getAllPermissions()->pluck('name'))->not->toContain($permission->name);
});

it('keeps unknown defined Gate abilities runnable', function (): void {
    [$user] = actingAsUserType(UserType::Student);
    $ability = 'foundation.permission-ceiling.callback';

    Gate::define($ability, fn (User $candidate): bool => $candidate->is($user));

    expect(Gate::forUser($user)->allows($ability))->toBeTrue();
});

it('does not grant canonical permissions to a developer without a role or direct grant', function (): void {
    seedRbac();
    $developer = User::factory()->developer()->create();
    $permission = 'financial.payment-gateways.update';

    expect($developer->hasPermissionTo($permission))->toBeFalse()
        ->and($developer->checkPermissionTo($permission))->toBeFalse()
        ->and($developer->can($permission))->toBeFalse()
        ->and($developer->getAllPermissions())->toBeEmpty();
});

it('keeps every canonical permission for developer seeded role', function (): void {
    [$developer] = actingAsUserType(UserType::Developer);

    $expectedPermissions = array_keys(config('permissions'));
    sort($expectedPermissions);

    $actualPermissions = $developer->getAllPermissions()->pluck('name')->all();
    sort($actualPermissions);

    expect($actualPermissions)->toBe($expectedPermissions);
});
