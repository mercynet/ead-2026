<?php

use App\Modules\Core\Enums\UserType;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Guard the canonical API error envelope: {data:null, errors:[{code,message}]}.
 *
 * The shape is enforced by the render() closures in bootstrap/app.php for
 * both the custom domain exceptions and the framework ones (Authentication,
 * Authorization, ModelNotFound/NotFoundHttp) on api/* requests — see
 * docs/specs/00-architecture/api-conventions.md.
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

    assertApiErrorEnvelope($response, 401, 'unauthenticated');
});

it('renders 403 access_denied in the canonical envelope', function (): void {
    [$student, $headers] = actingAsUserType(UserType::Student);

    // Payload must pass validation so the Gate (which runs after the
    // FormRequest) is what denies. Student lacks the create permission.
    $response = $this->postJson('/api/v1/assessment/questionnaires', [
        'title' => 'Sem permissão',
        'type' => 'standalone',
    ], $headers);

    assertApiErrorEnvelope($response, 403, 'access_denied');
});

it('renders 422 validation_error in the canonical envelope', function (): void {
    [$admin, $headers] = actingAsUserType(UserType::Admin);

    $response = $this->postJson('/api/v1/learning/courses', [], $headers);

    assertApiErrorEnvelope($response, 422, 'validation_error');
});

it('renders findOrFail 404 in the canonical envelope', function (): void {
    [$developer, $headers] = actingAsUserType(UserType::Developer);

    // ShowQuestionnaireAction uses findOrFail → ModelNotFoundException.
    // Developer is global: no X-Tenant-ID needed.
    $response = $this->getJson('/api/v1/assessment/questionnaires/999999', $headers);

    assertApiErrorEnvelope($response, 404, 'not_found');
});
