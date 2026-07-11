<?php

use App\Modules\Core\Enums\UserType;
use App\Modules\Core\Models\Tenant;
use App\Modules\Core\Models\User;
use App\Modules\Learning\Models\Course;

function makeCourseForTenant(Tenant $tenant, ?User $instructor = null): Course
{
    return Course::factory()->create([
        'tenant_id' => $tenant->id,
        'instructor_id' => $instructor?->id,
        'status' => 'draft',
    ]);
}

it('creates a course material for an authorized user in the current tenant', function (): void {
    $tenant = makeTenant(['domain' => 'tenant-a.local']);
    [$user, $headers] = actingAsUserType(UserType::Instructor, $tenant);
    $course = makeCourseForTenant($tenant, $user);

    $response = $this->postJson("/api/v1/learning/courses/{$course->id}/materials", [
        'file_path' => 'tenants/'.$tenant->id.'/materials/intro-guide.pdf',
    ], $headers);

    $response->assertCreated()
        ->assertJsonPath('data.course_id', $course->id)
        ->assertJsonPath('data.instructor_id', $user->id)
        ->assertJsonPath('data.file_path', 'tenants/'.$tenant->id.'/materials/intro-guide.pdf');

    $this->assertDatabaseHas('course_materials', [
        'tenant_id' => $tenant->id,
        'course_id' => $course->id,
        'instructor_id' => $user->id,
        'file_path' => 'tenants/'.$tenant->id.'/materials/intro-guide.pdf',
    ]);
});

it('returns 403 for a student without permission when creating a course material', function (): void {
    $tenant = makeTenant(['domain' => 'tenant-a.local']);
    [, $headers] = actingAsUserType(UserType::Student, $tenant);
    $course = makeCourseForTenant($tenant);

    $this->postJson("/api/v1/learning/courses/{$course->id}/materials", [
        'file_path' => 'tenants/'.$tenant->id.'/materials/intro-guide.pdf',
    ], $headers)->assertForbidden();
});

it('returns 401 without authentication when creating a course material', function (): void {
    $tenant = makeTenant(['domain' => 'tenant-a.local']);
    $course = makeCourseForTenant($tenant);

    $this->postJson("/api/v1/learning/courses/{$course->id}/materials", [
        'file_path' => 'tenants/'.$tenant->id.'/materials/intro-guide.pdf',
    ], tenantHeaders($tenant))->assertUnauthorized();
});

it('returns 404 for a missing or foreign course when creating a course material', function (): void {
    $tenantA = makeTenant(['domain' => 'tenant-a.local']);
    $tenantB = makeTenant(['domain' => 'tenant-b.local']);
    [, $headers] = actingAsUserType(UserType::Admin, $tenantA);
    $foreignCourse = makeCourseForTenant($tenantB);

    $this->postJson('/api/v1/learning/courses/999999/materials', [
        'file_path' => 'tenants/'.$tenantA->id.'/materials/intro-guide.pdf',
    ], $headers)->assertNotFound();

    $response = $this->postJson("/api/v1/learning/courses/{$foreignCourse->id}/materials", [
        'file_path' => 'tenants/'.$tenantA->id.'/materials/intro-guide.pdf',
    ], $headers);

    assertTenantIsolation($response);
});

it('returns 422 for an invalid course material payload', function (): void {
    $tenant = makeTenant(['domain' => 'tenant-a.local']);
    [, $headers] = actingAsUserType(UserType::Admin, $tenant);
    $course = makeCourseForTenant($tenant);

    assertApiErrorEnvelope(
        $this->postJson("/api/v1/learning/courses/{$course->id}/materials", [
            'file_path' => '',
        ], $headers),
        422,
        'validation_error'
    );
});

it('rejects a material file path outside the current tenant folder', function (string $filePathTemplate): void {
    $tenantA = makeTenant(['domain' => 'tenant-a.local']);
    $tenantB = makeTenant(['domain' => 'tenant-b.local']);
    [, $headers] = actingAsUserType(UserType::Admin, $tenantA);
    $course = makeCourseForTenant($tenantA);

    $filePath = str_replace(
        ['{tenantA}', '{tenantB}'],
        [(string) $tenantA->id, (string) $tenantB->id],
        $filePathTemplate,
    );

    assertApiErrorEnvelope(
        $this->postJson("/api/v1/learning/courses/{$course->id}/materials", [
            'file_path' => $filePath,
        ], $headers),
        422,
        'validation_error'
    );

    $this->assertDatabaseMissing('course_materials', ['file_path' => $filePath]);
})->with([
    'other tenant prefix' => 'tenants/{tenantB}/materials/leak.pdf',
    'path traversal' => 'tenants/{tenantA}/../{tenantB}/materials/leak.pdf',
    'backslash segment' => 'tenants/{tenantA}/materials\\..\\leak.pdf',
    'arbitrary bucket key' => 'shared/materials/leak.pdf',
]);
