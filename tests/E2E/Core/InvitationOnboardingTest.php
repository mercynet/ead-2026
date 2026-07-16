<?php

use App\Modules\Core\Enums\UserType;

/**
 * Fluxo de onboarding tenant-bound de ponta a ponta:
 * admin convida → convidado aceita → login → acessa o catálogo do tenant.
 */
it('onboards an invited member end to end: invite → accept → login → catalog', function (): void {
    [$admin, $adminHeaders] = actingAsUserType(UserType::Admin);
    $tenant = $admin->tenant;

    // 1. Admin emite o convite e recebe o token opaco uma única vez.
    $token = $this->postJson('/api/v1/core/invitations', [
        'email' => 'aluno@example.com',
        'role' => 'student',
    ], $adminHeaders)
        ->assertCreated()
        ->json('data.token');

    expect($token)->toBeString()->not->toBeEmpty();

    // 2. Convidado aceita: usuário criado com tenant/email/papel fixos do convite.
    $this->postJson('/api/v1/core/invitations/accept', [
        'token' => $token,
        'name' => 'Aluno Convidado',
        'password' => 'senha-forte-123',
        'password_confirmation' => 'senha-forte-123',
    ])
        ->assertCreated()
        ->assertJsonPath('data.email', 'aluno@example.com')
        ->assertJsonPath('data.tenant_id', $tenant->id)
        ->assertJsonPath('data.role', 'student');

    // 3. Login com as credenciais recém-criadas, no contexto do tenant.
    $loginToken = $this->postJson('/api/v1/core/auth/login', [
        'email' => 'aluno@example.com',
        'password' => 'senha-forte-123',
    ], tenantHeaders($tenant))
        ->assertOk()
        ->json('data.token');

    expect($loginToken)->toBeString()->not->toBeEmpty();

    // 4. Já autenticado, o aluno alcança o catálogo do tenant.
    $this->getJson('/api/v1/learning/catalog/courses', tenantHeaders($tenant, $loginToken))
        ->assertOk();
});
