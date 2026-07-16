<?php

use App\Modules\Core\Models\User;
use App\Modules\Core\Notifications\PasswordResetNotification;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;

/**
 * Fluxo de recuperação de senha ponta a ponta:
 * forgot → token por e-mail → reset → sessões antigas revogadas → login com a
 * nova senha → acesso autenticado; a senha antiga deixa de funcionar.
 */
it('recovers access end to end: forgot → reset → login with the new password', function (): void {
    Notification::fake();

    $tenant = makeTenant();
    $user = User::factory()->forTenant($tenant)->create([
        'email' => 'john@example.com',
        'password' => Hash::make('old-password-123'),
    ]);
    $oldSession = $user->createToken('old-session')->plainTextToken;

    // 1. Pedido de redefinição (resposta genérica).
    $this->postJson('/api/v1/core/auth/password/forgot', [
        'email' => 'john@example.com',
    ], tenantHeaders($tenant))->assertOk();

    // Captura o token opaco entregue por e-mail.
    $captured = null;
    Notification::assertSentTo($user, PasswordResetNotification::class, function (PasswordResetNotification $notification) use (&$captured): bool {
        $captured = $notification->token;

        return true;
    });
    expect($captured)->toBeString()->not->toBeEmpty();

    // 2. Redefine a senha com o token.
    $this->postJson('/api/v1/core/auth/password/reset', [
        'token' => $captured,
        'password' => 'new-password-456',
        'password_confirmation' => 'new-password-456',
    ])->assertOk();

    // Sessão antiga revogada pela troca de senha.
    $this->getJson('/api/v1/core/auth/me', tenantHeaders($tenant, $oldSession))
        ->assertUnauthorized();

    // 3. Login com a nova senha, no contexto do tenant.
    $newToken = $this->postJson('/api/v1/core/auth/login', [
        'email' => 'john@example.com',
        'password' => 'new-password-456',
    ], tenantHeaders($tenant))
        ->assertOk()
        ->json('data.token');

    // 4. Acesso autenticado com a nova sessão.
    $this->getJson('/api/v1/core/auth/me', tenantHeaders($tenant, $newToken))
        ->assertOk()
        ->assertJsonPath('data.email', 'john@example.com');

    // A senha antiga não autentica mais.
    $this->postJson('/api/v1/core/auth/login', [
        'email' => 'john@example.com',
        'password' => 'old-password-123',
    ], tenantHeaders($tenant))->assertUnauthorized();
});
