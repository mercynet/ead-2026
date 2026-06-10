<?php

use App\Enums\UserType;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Guard the canonical API error envelope: {data:null, errors:[{code,message}]}.
 *
 * The shape is enforced by the render() closures in bootstrap/app.php. These
 * tests exercise the real HTTP stack for the statuses that currently produce
 * the envelope, and flag (as debt) the statuses that still leak Laravel's
 * default JSON — see docs/specs/00-architecture/api-conventions.md.
 */
uses(RefreshDatabase::class);

it('renders 422 tenant_not_resolved in the canonical envelope', function (): void {
    // Guest hits a tenant-required route with no X-Tenant-ID header.
    $response = $this->postJson('/api/v1/core/users', []);

    assertApiErrorEnvelope($response, 422, 'tenant_not_resolved');
});

it('renders 404 not_found in the canonical envelope', function (): void {
    // Catalog course show throws the custom ResourceNotFoundException; the
    // Eloquent findOrFail paths still leak Laravel's default 404 (separate debt).
    [$admin, $headers] = actingAsUserType(UserType::Admin);

    $response = $this->getJson('/api/v1/learning/catalog/courses/no-such-slug', $headers);

    assertApiErrorEnvelope($response, 404, 'not_found');
});

it('renders 401 unauthenticated in the canonical envelope', function (): void {
    [$developer, $headers] = actingAsUserType(UserType::Developer);

    // Authenticated route, no token → must surface the {data,errors} envelope.
    $response = $this->getJson('/api/v1/assessment/questionnaires', [
        'X-Tenant-ID' => $headers['X-Tenant-ID'] ?? '1',
    ]);

    assertApiErrorEnvelope($response, 401);
})->todo('debt: Sanctum 401 returns Laravel default {"message":...}, not the canonical envelope');

it('renders 403 access_denied in the canonical envelope', function (): void {
    [$student, $headers] = actingAsUserType(UserType::Student);

    // Student lacks the create permission → Gate denies.
    $response = $this->postJson('/api/v1/assessment/questionnaires', [
        'title' => 'x',
        'type' => 'quiz',
    ], $headers);

    assertApiErrorEnvelope($response, 403, 'access_denied');
})->todo('debt: Gate AuthorizationException returns Laravel default 403, not the canonical envelope');
