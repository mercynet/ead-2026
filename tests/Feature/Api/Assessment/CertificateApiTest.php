<?php

use App\Models\Certificate;
use App\Models\Enrollment;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\PermissionsSeeder;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed([PermissionsSeeder::class, RolesSeeder::class]);

    $this->tenant = Tenant::query()->create([
        'name' => 'Tenant A',
        'domain' => 'tenant-a.local',
        'database' => null,
        'is_active' => true,
    ]);

    $this->developer = User::query()->create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Developer',
        'email' => 'developer@example.com',
        'password' => bcrypt('password'),
    ]);

    $this->developer->assignRole('developer');

    $this->enrollment = Enrollment::factory()->create([
        'tenant_id' => $this->tenant->id,
        'user_id' => $this->developer->id,
    ]);

    $this->certificate = Certificate::factory()->create([
        'tenant_id' => $this->tenant->id,
        'user_id' => $this->developer->id,
        'enrollment_id' => $this->enrollment->id,
        'certificate_number' => 'CERT-2026-ABCD1234',
    ]);
});

it('lists certificates', function (): void {
    Sanctum::actingAs($this->developer);

    $response = $this->getJson('/api/v1/assessment/certificates', [
        'X-Tenant-ID' => (string) $this->tenant->id,
    ]);

    $response->assertSuccessful();
    $response->assertJsonStructure([
        'data' => [
            '*' => ['id', 'certificate_number', 'status', 'issued_at'],
        ],
    ]);
});

it('shows a certificate', function (): void {
    Sanctum::actingAs($this->developer);

    $response = $this->getJson(
        "/api/v1/assessment/certificates/{$this->certificate->id}",
        ['X-Tenant-ID' => (string) $this->tenant->id],
    );

    $response->assertSuccessful();
    $response->assertJsonPath('data.certificate_number', $this->certificate->certificate_number);
});

it('verifies a valid certificate', function (): void {
    $response = $this->getJson('/api/v1/assessment/certificates/verify/CERT-2026-ABCD1234');

    $response->assertSuccessful();
    $response->assertJsonStructure([
        'valid',
        'certificate' => ['certificate_number', 'status', 'issued_at'],
    ]);
});

it('returns invalid for non-existent certificate', function (): void {
    $response = $this->getJson('/api/v1/assessment/certificates/verify/INVALID-CERT');

    $response->assertSuccessful();
    $response->assertJsonPath('valid', false);
});

it('returns invalid for revoked certificate', function (): void {
    $this->certificate->update(['status' => 'revoked']);

    $response = $this->getJson('/api/v1/assessment/certificates/verify/CERT-2026-ABCD1234');

    $response->assertSuccessful();
    $response->assertJsonPath('valid', false);
});
