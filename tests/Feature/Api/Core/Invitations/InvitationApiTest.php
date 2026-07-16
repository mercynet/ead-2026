<?php

use App\Modules\Core\Enums\UserType;
use App\Modules\Core\Models\Invitation;
use App\Modules\Core\Models\User;
use Illuminate\Support\Facades\Hash;

/*
|--------------------------------------------------------------------------
| Criação de convite (POST /api/v1/core/invitations)
|--------------------------------------------------------------------------
*/

it('lets a tenant admin issue an invitation and returns the token once', function (): void {
    [$admin, $headers] = actingAsUserType(UserType::Admin);

    $response = $this->postJson('/api/v1/core/invitations', [
        'email' => 'convidado@example.com',
        'role' => 'student',
    ], $headers);

    $response
        ->assertCreated()
        ->assertJsonPath('data.email', 'convidado@example.com')
        ->assertJsonPath('data.role', 'student')
        ->assertJsonStructure(['data' => ['id', 'email', 'role', 'token', 'expires_at']]);

    $token = $response->json('data.token');
    expect($token)->toBeString()->not->toBeEmpty();

    $invitation = Invitation::query()->firstOrFail();
    expect($invitation->tenant_id)->toBe($admin->tenant_id)
        ->and($invitation->email)->toBe('convidado@example.com')
        ->and($invitation->role)->toBe('student')
        ->and($invitation->invited_by)->toBe($admin->id)
        ->and($invitation->token_hash)->toBe(Invitation::hashToken($token))
        ->and($invitation->getRawOriginal('token_hash'))->not->toBe($token);
});

it('allows inviting an instructor', function (): void {
    [$admin, $headers] = actingAsUserType(UserType::Admin);

    $this->postJson('/api/v1/core/invitations', [
        'email' => 'prof@example.com',
        'role' => 'instructor',
    ], $headers)
        ->assertCreated()
        ->assertJsonPath('data.role', 'instructor');
});

it('rejects unauthenticated invitation creation', function (): void {
    $tenant = makeTenant();

    $this->postJson('/api/v1/core/invitations', [
        'email' => 'convidado@example.com',
        'role' => 'student',
    ], tenantHeaders($tenant))
        ->assertUnauthorized();
});

it('forbids non-admins from issuing invitations', function (): void {
    [$instructor, $headers] = actingAsUserType(UserType::Instructor);

    assertApiErrorEnvelope(
        $this->postJson('/api/v1/core/invitations', [
            'email' => 'convidado@example.com',
            'role' => 'student',
        ], $headers),
        403,
        'access_denied',
    );

    expect(Invitation::query()->count())->toBe(0);
});

it('does not leak email existence to unauthorized callers (no enumeration oracle)', function (): void {
    [$instructor, $headers] = actingAsUserType(UserType::Instructor);
    User::factory()->forTenant($instructor->tenant)->create(['email' => 'existente@example.com']);

    // Mesmo com um email que existe no tenant, um não-admin recebe 403 (não 422):
    // a autorização precede qualquer checagem de unicidade.
    assertApiErrorEnvelope(
        $this->postJson('/api/v1/core/invitations', [
            'email' => 'existente@example.com',
            'role' => 'student',
        ], $headers),
        403,
        'access_denied',
    );
});

it('rejects role escalation to admin or developer', function (string $role): void {
    [$admin, $headers] = actingAsUserType(UserType::Admin);

    assertApiErrorEnvelope(
        $this->postJson('/api/v1/core/invitations', [
            'email' => 'convidado@example.com',
            'role' => $role,
        ], $headers),
        422,
        'validation_error',
    );

    expect(Invitation::query()->count())->toBe(0);
})->with(['admin', 'developer']);

it('rejects inviting an email that already belongs to a user in the tenant', function (): void {
    [$admin, $headers] = actingAsUserType(UserType::Admin);

    User::factory()->forTenant($admin->tenant)->create(['email' => 'existente@example.com']);

    assertApiErrorEnvelope(
        $this->postJson('/api/v1/core/invitations', [
            'email' => 'existente@example.com',
            'role' => 'student',
        ], $headers),
        422,
        'validation_error',
    );
});

it('validates the invitation payload', function (): void {
    [$admin, $headers] = actingAsUserType(UserType::Admin);

    assertApiErrorEnvelope(
        $this->postJson('/api/v1/core/invitations', ['role' => 'student'], $headers),
        422,
        'validation_error',
    );
});

it('forbids an admin from issuing invitations into another tenant (IDOR)', function (): void {
    $tenantB = makeTenant();
    [$adminA, $headersA] = actingAsUserType(UserType::Admin);

    // Admin do tenant A tenta usar o X-Tenant-ID do tenant B.
    $headersCrossTenant = tenantHeaders($tenantB, str($headersA['Authorization'])->after('Bearer ')->toString());

    assertTenantIsolation(
        $this->postJson('/api/v1/core/invitations', [
            'email' => 'convidado@example.com',
            'role' => 'student',
        ], $headersCrossTenant),
    );

    expect(Invitation::query()->where('tenant_id', $tenantB->id)->count())->toBe(0);
});

/*
|--------------------------------------------------------------------------
| Aceite de convite (POST /api/v1/core/invitations/accept)
|--------------------------------------------------------------------------
*/

it('accepts a valid invitation, creating the user with the fixed tenant, email and role', function (): void {
    seedRbac();
    $tenant = makeTenant();
    $token = 'valid-token-abc';
    $invitation = Invitation::factory()->forTenant($tenant)->role(UserType::Student)->withToken($token)->create([
        'email' => 'novo@example.com',
    ]);

    $response = $this->postJson('/api/v1/core/invitations/accept', [
        'token' => $token,
        'name' => 'Novo Aluno',
        'password' => 'senha-forte-123',
        'password_confirmation' => 'senha-forte-123',
    ]);

    $response
        ->assertCreated()
        ->assertJsonPath('data.email', 'novo@example.com')
        ->assertJsonPath('data.tenant_id', $tenant->id)
        ->assertJsonPath('data.role', 'student');

    $user = User::query()->where('email', 'novo@example.com')->firstOrFail();
    expect($user->tenant_id)->toBe($tenant->id)
        ->and($user->user_type)->toBe(UserType::Student)
        ->and($user->hasRole('student'))->toBeTrue()
        ->and(Hash::check('senha-forte-123', $user->password))->toBeTrue();

    $invitation->refresh();
    expect($invitation->isAccepted())->toBeTrue()
        ->and($invitation->accepted_by)->toBe($user->id);
});

it('assigns the instructor role when the invitation carries it', function (): void {
    seedRbac();
    $tenant = makeTenant();
    $token = 'instructor-token';
    Invitation::factory()->forTenant($tenant)->role(UserType::Instructor)->withToken($token)->create([
        'email' => 'prof@example.com',
    ]);

    $this->postJson('/api/v1/core/invitations/accept', [
        'token' => $token,
        'name' => 'Professora',
        'password' => 'senha-forte-123',
        'password_confirmation' => 'senha-forte-123',
    ])->assertCreated();

    $user = User::query()->where('email', 'prof@example.com')->firstOrFail();
    expect($user->user_type)->toBe(UserType::Instructor)
        ->and($user->hasRole('instructor'))->toBeTrue();
});

it('rejects an unknown or tampered token generically', function (): void {
    seedRbac();
    $tenant = makeTenant();
    Invitation::factory()->forTenant($tenant)->withToken('the-real-token')->create();

    assertApiErrorEnvelope(
        $this->postJson('/api/v1/core/invitations/accept', [
            'token' => 'the-real-token-TAMPERED',
            'name' => 'Intruso',
            'password' => 'senha-forte-123',
            'password_confirmation' => 'senha-forte-123',
        ]),
        422,
        'invitation_invalid',
    );

    expect(User::query()->count())->toBe(0);
});

it('rejects an expired invitation generically', function (): void {
    seedRbac();
    $tenant = makeTenant();
    $token = 'expired-token';
    Invitation::factory()->forTenant($tenant)->withToken($token)->expired()->create([
        'email' => 'atrasado@example.com',
    ]);

    assertApiErrorEnvelope(
        $this->postJson('/api/v1/core/invitations/accept', [
            'token' => $token,
            'name' => 'Atrasado',
            'password' => 'senha-forte-123',
            'password_confirmation' => 'senha-forte-123',
        ]),
        422,
        'invitation_invalid',
    );

    expect(User::query()->where('email', 'atrasado@example.com')->count())->toBe(0);
});

it('rejects replaying an already-accepted invitation (single use)', function (): void {
    seedRbac();
    $tenant = makeTenant();
    $token = 'replay-token';
    Invitation::factory()->forTenant($tenant)->withToken($token)->create([
        'email' => 'unico@example.com',
    ]);

    $payload = [
        'token' => $token,
        'name' => 'Primeiro',
        'password' => 'senha-forte-123',
        'password_confirmation' => 'senha-forte-123',
    ];

    $this->postJson('/api/v1/core/invitations/accept', $payload)->assertCreated();

    assertApiErrorEnvelope(
        $this->postJson('/api/v1/core/invitations/accept', [...$payload, 'name' => 'Segundo']),
        422,
        'invitation_invalid',
    );

    expect(User::query()->where('email', 'unico@example.com')->count())->toBe(1);
});

it('fails gracefully when the email is taken between issue and accept (no 500)', function (): void {
    seedRbac();
    $tenant = makeTenant();
    $token = 'race-token';
    Invitation::factory()->forTenant($tenant)->withToken($token)->create([
        'email' => 'ocupado@example.com',
    ]);

    // Alguém passa a ocupar o email depois do convite emitido.
    User::factory()->forTenant($tenant)->create(['email' => 'ocupado@example.com']);

    assertApiErrorEnvelope(
        $this->postJson('/api/v1/core/invitations/accept', [
            'token' => $token,
            'name' => 'Tarde Demais',
            'password' => 'senha-forte-123',
            'password_confirmation' => 'senha-forte-123',
        ]),
        422,
        'invitation_invalid',
    );

    expect(User::query()->where('email', 'ocupado@example.com')->count())->toBe(1);
});

it('validates the accept payload', function (): void {
    assertApiErrorEnvelope(
        $this->postJson('/api/v1/core/invitations/accept', ['token' => 'x']),
        422,
        'validation_error',
    );
});
