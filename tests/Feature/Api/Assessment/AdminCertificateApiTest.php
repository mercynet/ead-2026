<?php

use App\Modules\Assessment\Models\Certificate;
use App\Modules\Core\Enums\UserType;

beforeEach(function (): void {
    $this->tenant = makeTenant();
    $this->certificate = Certificate::factory()->create([
        'tenant_id' => $this->tenant->id,
        'certificate_number' => 'CERT-2026-REVOKE01',
        'status' => 'issued',
    ]);
});

it('allows an admin to revoke a certificate in its tenant', function (): void {
    [, $headers] = actingAsUserType(UserType::Admin, $this->tenant);

    $response = $this->postJson(
        "/api/v1/admin/certificates/{$this->certificate->id}/revoke",
        [],
        $headers,
    );

    $response->assertSuccessful()
        ->assertJsonPath('data.id', $this->certificate->id)
        ->assertJsonPath('data.status', 'revoked');

    expect($this->certificate->refresh()->status)->toBe('revoked');

    $this->getJson(
        '/api/v1/assessment/certificates/verify/'.$this->certificate->certificate_number,
    )->assertSuccessful()->assertJsonPath('valid', false);
});

it('makes certificate revocation idempotent', function (): void {
    [, $headers] = actingAsUserType(UserType::Admin, $this->tenant);

    $this->postJson(
        "/api/v1/admin/certificates/{$this->certificate->id}/revoke",
        [],
        $headers,
    )->assertSuccessful();

    $this->postJson(
        "/api/v1/admin/certificates/{$this->certificate->id}/revoke",
        [],
        $headers,
    )->assertSuccessful()->assertJsonPath('data.status', 'revoked');
});

it('rejects unauthenticated certificate revocation', function (): void {
    assertApiErrorEnvelope(
        $this->postJson(
            "/api/v1/admin/certificates/{$this->certificate->id}/revoke",
            [],
            tenantHeaders($this->tenant),
        ),
        401,
        'unauthenticated',
    );
});

it('rejects certificate revocation from a non-admin area', function (): void {
    [, $headers] = actingAsUserType(UserType::Student, $this->tenant);

    assertApiErrorEnvelope(
        $this->postJson(
            "/api/v1/admin/certificates/{$this->certificate->id}/revoke",
            [],
            $headers,
        ),
        403,
        'area_forbidden',
    );
});

it('does not revoke a certificate from another tenant', function (): void {
    $otherTenant = makeTenant();
    [, $headers] = actingAsUserType(UserType::Admin, $otherTenant);

    assertApiErrorEnvelope(
        $this->postJson(
            "/api/v1/admin/certificates/{$this->certificate->id}/revoke",
            [],
            $headers,
        ),
        404,
        'not_found',
    );

    expect($this->certificate->refresh()->status)->toBe('issued');
});
