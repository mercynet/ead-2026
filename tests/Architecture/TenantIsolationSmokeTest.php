<?php

use App\Modules\Assessment\Models\Questionnaire;
use App\Modules\Core\Enums\UserType;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Smoke test: a tenant never reaches another tenant's resource over HTTP.
 *
 * One concrete end-to-end probe (Assessment) standing in for the invariant.
 * Per-endpoint isolation lives in the Feature suite; this is the neutral
 * arbiter that fails loudly if the boundary ever opens.
 */
uses(RefreshDatabase::class);

it('denies an admin reaching a questionnaire owned by another tenant', function (): void {
    $tenantA = makeTenant(['domain' => 'tenant-a.local']);
    $tenantB = makeTenant(['domain' => 'tenant-b.local']);

    $questionnaireOfA = Questionnaire::factory()->create([
        'tenant_id' => $tenantA->id,
    ]);

    // Admin scoped to tenant B asks for tenant A's questionnaire.
    [$adminB, $headers] = actingAsUserType(UserType::Admin, $tenantB);

    $response = $this->getJson(
        "/api/v1/assessment/questionnaires/{$questionnaireOfA->id}",
        $headers,
    );

    assertTenantIsolation($response);
});
