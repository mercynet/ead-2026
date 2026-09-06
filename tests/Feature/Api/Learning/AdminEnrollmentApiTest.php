<?php

use App\Modules\Core\Enums\UserType;
use App\Modules\Core\Models\User;
use App\Modules\Financial\Models\Order;
use App\Modules\Learning\Models\Course;
use App\Modules\Learning\Models\Enrollment;

it('creates and manages an enrollment through the admin area', function (): void {
    $tenant = makeTenant(['domain' => 'admin-enrollment.local']);
    [, $headers] = actingAsUserType(UserType::Admin, $tenant);
    $course = Course::query()->create([
        'tenant_id' => $tenant->id,
        'title' => 'Admin Enrollment Course',
        'slug' => 'admin-enrollment-course',
        'description' => 'Course description',
        'status' => 'published',
        'price_cents' => 12900,
        'access_days' => 30,
        'is_featured' => false,
    ]);
    $student = User::factory()->forTenant($tenant)->student()->create();

    $created = $this->postJson('/api/v1/admin/enrollments', [
        'course_id' => $course->id,
        'user_id' => $student->id,
    ], $headers)
        ->assertCreated()
        ->assertJsonPath('data.status', 'active')
        ->assertJsonPath('data.user_id', $student->id)
        ->assertJsonPath('data.course_id', $course->id);

    $enrollment = Enrollment::query()->sole();

    expect(Order::query()->count())->toBe(1)
        ->and($created->json('data.id'))->toBe($enrollment->id);

    $this->getJson('/api/v1/admin/enrollments', $headers)
        ->assertOk()
        ->assertJsonPath('data.0.id', $enrollment->id);

    $this->patchJson("/api/v1/admin/enrollments/{$enrollment->id}", [
        'progress_percentage' => 25,
    ], $headers)
        ->assertOk()
        ->assertJsonPath('data.progress_percentage', 25);

    $this->deleteJson("/api/v1/admin/enrollments/{$enrollment->id}", [], $headers)
        ->assertOk()
        ->assertJsonPath('data.message', 'Enrollment cancelled successfully.');

    expect($enrollment->fresh()->status)->toBe('cancelled');
});

it('enforces the admin enrollment surface, tenant isolation, and scope-safe input', function (): void {
    $tenant = makeTenant(['domain' => 'admin-enrollment-a.local']);
    $otherTenant = makeTenant(['domain' => 'admin-enrollment-b.local']);
    [, $headers] = actingAsUserType(UserType::Admin, $tenant);
    $otherCourse = Course::query()->create([
        'tenant_id' => $otherTenant->id,
        'title' => 'Foreign Course',
        'slug' => 'foreign-admin-enrollment-course',
        'description' => 'Course description',
        'status' => 'published',
        'price_cents' => 0,
        'access_days' => 30,
        'is_featured' => false,
    ]);
    $otherStudent = User::factory()->forTenant($otherTenant)->student()->create();

    $this->postJson('/api/v1/admin/enrollments', [
        'course_id' => $otherCourse->id,
        'user_id' => $otherStudent->id,
        'tenant_id' => $otherTenant->id,
    ], $headers)
        ->assertUnprocessable()
        ->assertJsonPath('errors.0.code', 'validation_error');

    $this->getJson('/api/v1/admin/enrollments/999999', $headers)
        ->assertNotFound()
        ->assertJsonPath('errors.0.code', 'not_found');

});

it('keeps the admin enrollment surface exclusive to authenticated admins', function (): void {
    $tenant = makeTenant(['domain' => 'admin-enrollment-persona.local']);
    seedRbac();
    $student = User::factory()->forTenant($tenant)->student()->create();
    $student->assignRole(UserType::Student->value);
    $studentHeaders = tenantHeaders($tenant, $student->createToken('student-admin-surface')->plainTextToken);

    $this->getJson('/api/v1/admin/enrollments', $studentHeaders)
        ->assertForbidden()
        ->assertJsonPath('errors.0.code', 'area_forbidden');

});

it('requires authentication for the admin enrollment surface', function (): void {
    $tenant = makeTenant(['domain' => 'admin-enrollment-guest.local']);

    $this->postJson('/api/v1/admin/enrollments', [], tenantHeaders($tenant))
        ->assertUnauthorized()
        ->assertJsonPath('errors.0.code', 'unauthenticated');
});
