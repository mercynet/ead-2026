<?php

use App\Modules\Core\Models\PasswordReset;
use App\Modules\Core\Models\User;
use App\Modules\Core\Notifications\PasswordResetNotification;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\PersonalAccessToken;

/*
|--------------------------------------------------------------------------
| Esqueci a senha (POST /api/v1/core/auth/password/forgot)
|--------------------------------------------------------------------------
*/

it('issues a reset request and notifies the user when the email exists in the tenant', function (): void {
    Notification::fake();
    $tenant = makeTenant();
    $user = User::factory()->forTenant($tenant)->create(['email' => 'john@example.com']);

    $this->postJson('/api/v1/core/auth/password/forgot', [
        'email' => 'john@example.com',
    ], tenantHeaders($tenant))
        ->assertOk()
        ->assertJsonPath('data.message', 'Se o email existir, enviaremos instruções de redefinição.');

    Notification::assertSentTo($user, PasswordResetNotification::class);

    $reset = PasswordReset::query()->where('tenant_id', $tenant->id)->where('email', 'john@example.com')->firstOrFail();
    expect($reset->isPending())->toBeTrue()
        ->and($reset->getRawOriginal('token_hash'))->toHaveLength(64);
});

it('supports the canonical password forgot URL', function (): void {
    Notification::fake();
    $tenant = makeTenant();
    $user = User::factory()->forTenant($tenant)->create(['email' => 'canonical@example.com']);

    $this->postJson('/api/v1/auth/password/forgot', [
        'email' => 'canonical@example.com',
    ], tenantHeaders($tenant))
        ->assertOk()
        ->assertJsonPath('data.message', 'Se o email existir, enviaremos instruções de redefinição.');

    Notification::assertSentTo($user, PasswordResetNotification::class);
});

it('responds generically and sends nothing when the email does not exist (no enumeration)', function (): void {
    Notification::fake();
    $tenant = makeTenant();

    $this->postJson('/api/v1/core/auth/password/forgot', [
        'email' => 'ghost@example.com',
    ], tenantHeaders($tenant))
        ->assertOk()
        ->assertJsonPath('data.message', 'Se o email existir, enviaremos instruções de redefinição.');

    Notification::assertNothingSent();
    expect(PasswordReset::query()->count())->toBe(0);
});

it('is tenant-scoped: an email from another tenant does not trigger a reset', function (): void {
    Notification::fake();
    $tenantA = makeTenant();
    $tenantB = makeTenant();
    User::factory()->forTenant($tenantB)->create(['email' => 'shared@example.com']);

    $this->postJson('/api/v1/core/auth/password/forgot', [
        'email' => 'shared@example.com',
    ], tenantHeaders($tenantA))
        ->assertOk();

    Notification::assertNothingSent();
    expect(PasswordReset::query()->count())->toBe(0);
});

it('rejects forgot without tenant context', function (): void {
    assertApiErrorEnvelope(
        $this->postJson('/api/v1/core/auth/password/forgot', ['email' => 'john@example.com']),
        422,
        'tenant_not_resolved',
    );
});

it('validates the forgot payload', function (): void {
    $tenant = makeTenant();

    assertApiErrorEnvelope(
        $this->postJson('/api/v1/core/auth/password/forgot', ['email' => 'not-an-email'], tenantHeaders($tenant)),
        422,
        'validation_error',
    );
});

it('rotates the token: a new forgot request invalidates the previous pending one', function (): void {
    Notification::fake();
    $tenant = makeTenant();
    $user = User::factory()->forTenant($tenant)->create(['email' => 'john@example.com']);

    $this->postJson('/api/v1/core/auth/password/forgot', ['email' => 'john@example.com'], tenantHeaders($tenant))->assertOk();
    $this->postJson('/api/v1/core/auth/password/forgot', ['email' => 'john@example.com'], tenantHeaders($tenant))->assertOk();

    $resets = PasswordReset::query()
        ->where('tenant_id', $tenant->id)
        ->where('email', 'john@example.com')
        ->get();

    // Invariante "um único token válido por vez": dois pedidos → dois registros,
    // exatamente um pendente (o segundo invalidou o primeiro sob lock/transação).
    expect($resets)->toHaveCount(2)
        ->and($resets->filter->isPending())->toHaveCount(1);

    Notification::assertSentToTimes($user, PasswordResetNotification::class, 2);
});

it('queues the reset notification instead of sending it synchronously (anti-timing)', function (): void {
    expect(new PasswordResetNotification('any-token'))
        ->toBeInstanceOf(Illuminate\Contracts\Queue\ShouldQueue::class);
});

/*
|--------------------------------------------------------------------------
| Redefinir senha (POST /api/v1/core/auth/password/reset)
|--------------------------------------------------------------------------
*/

it('resets the password with a valid token and revokes all sessions', function (): void {
    $tenant = makeTenant();
    $user = User::factory()->forTenant($tenant)->create([
        'email' => 'john@example.com',
        'password' => Hash::make('old-password-123'),
    ]);
    $user->createToken('device-a');
    $user->createToken('device-b');
    $token = 'valid-reset-token';
    $reset = PasswordReset::factory()->forTenant($tenant)->withToken($token)->create(['email' => 'john@example.com']);

    $this->postJson('/api/v1/core/auth/password/reset', [
        'token' => $token,
        'password' => 'new-password-456',
        'password_confirmation' => 'new-password-456',
    ])
        ->assertOk()
        ->assertJsonPath('data.password_reset', true);

    expect(Hash::check('new-password-456', $user->fresh()->password))->toBeTrue()
        ->and(PersonalAccessToken::query()->where('tokenable_id', $user->id)->count())->toBe(0);

    $reset->refresh();
    expect($reset->isUsed())->toBeTrue();
});

it('rejects an expired reset token generically', function (): void {
    $tenant = makeTenant();
    User::factory()->forTenant($tenant)->create(['email' => 'john@example.com']);
    $token = 'expired-token';
    PasswordReset::factory()->forTenant($tenant)->withToken($token)->expired()->create(['email' => 'john@example.com']);

    assertApiErrorEnvelope(
        $this->postJson('/api/v1/core/auth/password/reset', [
            'token' => $token,
            'password' => 'new-password-456',
            'password_confirmation' => 'new-password-456',
        ]),
        422,
        'password_reset_invalid',
    );
});

it('rejects an already-used reset token generically', function (): void {
    $tenant = makeTenant();
    User::factory()->forTenant($tenant)->create(['email' => 'john@example.com']);
    $token = 'used-token';
    PasswordReset::factory()->forTenant($tenant)->withToken($token)->used()->create(['email' => 'john@example.com']);

    assertApiErrorEnvelope(
        $this->postJson('/api/v1/core/auth/password/reset', [
            'token' => $token,
            'password' => 'new-password-456',
            'password_confirmation' => 'new-password-456',
        ]),
        422,
        'password_reset_invalid',
    );
});

it('rejects an unknown or tampered reset token generically', function (): void {
    $tenant = makeTenant();
    User::factory()->forTenant($tenant)->create(['email' => 'john@example.com']);
    PasswordReset::factory()->forTenant($tenant)->withToken('the-real-token')->create(['email' => 'john@example.com']);

    assertApiErrorEnvelope(
        $this->postJson('/api/v1/core/auth/password/reset', [
            'token' => 'the-real-token-TAMPERED',
            'password' => 'new-password-456',
            'password_confirmation' => 'new-password-456',
        ]),
        422,
        'password_reset_invalid',
    );
});

it('rejects replaying a reset token (single use)', function (): void {
    $tenant = makeTenant();
    User::factory()->forTenant($tenant)->create([
        'email' => 'john@example.com',
        'password' => Hash::make('old-password-123'),
    ]);
    $token = 'replay-token';
    PasswordReset::factory()->forTenant($tenant)->withToken($token)->create(['email' => 'john@example.com']);

    $payload = [
        'token' => $token,
        'password' => 'new-password-456',
        'password_confirmation' => 'new-password-456',
    ];

    $this->postJson('/api/v1/core/auth/password/reset', $payload)->assertOk();

    assertApiErrorEnvelope(
        $this->postJson('/api/v1/core/auth/password/reset', $payload),
        422,
        'password_reset_invalid',
    );
});

it('validates the reset payload', function (): void {
    assertApiErrorEnvelope(
        $this->postJson('/api/v1/core/auth/password/reset', ['token' => 'x', 'password' => 'short']),
        422,
        'validation_error',
    );
});
