<?php

use App\Modules\Core\Enums\UserType;
use App\Modules\Core\Models\User;
use App\Modules\Learning\Models\Course;
use App\Modules\Learning\Models\Enrollment;

it('creates enrollment and returns resource payload', function (): void {
    $tenant = makeTenant(['domain' => 'tenant-a.local']);
    [$admin, $headers] = actingAsUserType(UserType::Admin, $tenant);

    $course = Course::query()->create([
        'tenant_id' => $tenant->id,
        'title' => 'Test Course',
        'slug' => 'test-course',
        'description' => 'Course description',
        'status' => 'published',
        'price_cents' => 1000,
        'access_days' => 30,
        'is_featured' => false,
    ]);

    $targetUser = User::factory()->forTenant($tenant)->student()->create();

    $response = $this->postJson('/api/v1/learning/enrollments', [
        'course_id' => $course->id,
        'user_id' => $targetUser->id,
    ], $headers);

    $response->assertCreated()
        ->assertJsonPath('data.status', 'active')
        ->assertJsonPath('data.progress_percentage', 0)
        ->assertJsonPath('data.course.id', $course->id)
        ->assertJsonPath('data.course.slug', 'test-course')
        ->assertJsonMissingPath('data.completed_at');
});

it('defaults user id to authenticated user when omitted', function (): void {
    $tenant = makeTenant(['domain' => 'tenant-a.local']);
    [$admin, $headers] = actingAsUserType(UserType::Admin, $tenant);

    $course = Course::query()->create([
        'tenant_id' => $tenant->id,
        'title' => 'Test Course',
        'slug' => 'test-course',
        'description' => 'Course description',
        'status' => 'published',
        'price_cents' => 1000,
        'access_days' => 30,
        'is_featured' => false,
    ]);

    $this->postJson('/api/v1/learning/enrollments', [
        'course_id' => $course->id,
    ], $headers)
        ->assertCreated()
        ->assertJsonPath('data.course.id', $course->id);

    expect(Enrollment::query()->where('tenant_id', $tenant->id)->where('user_id', $admin->id)->where('course_id', $course->id)->exists())->toBeTrue();
});

it('returns null when user has no enrollment for course', function (): void {
    $tenant = makeTenant(['domain' => 'tenant-a.local']);
    [$student, $headers] = actingAsUserType(UserType::Student, $tenant);

    $course = Course::query()->create([
        'tenant_id' => $tenant->id,
        'title' => 'Test Course',
        'slug' => 'test-course',
        'description' => 'Course description',
        'status' => 'published',
        'price_cents' => 1000,
        'access_days' => 30,
        'is_featured' => false,
    ]);

    $this->getJson("/api/v1/learning/courses/{$course->id}/enrollment", $headers)
        ->assertSuccessful()
        ->assertJsonPath('data', null);
});

it('returns enrollment status for enrolled user', function (): void {
    $tenant = makeTenant(['domain' => 'tenant-a.local']);
    [$student, $headers] = actingAsUserType(UserType::Student, $tenant);

    $course = Course::query()->create([
        'tenant_id' => $tenant->id,
        'title' => 'Test Course',
        'slug' => 'test-course',
        'description' => 'Course description',
        'status' => 'published',
        'price_cents' => 1000,
        'access_days' => 30,
        'is_featured' => false,
    ]);

    Enrollment::query()->create([
        'tenant_id' => $tenant->id,
        'user_id' => $student->id,
        'course_id' => $course->id,
        'status' => 'active',
        'enrolled_at' => now(),
        'access_expires_at' => now()->addDays(30),
        'progress_percentage' => 45,
    ]);

    $this->getJson("/api/v1/learning/courses/{$course->id}/enrollment", $headers)
        ->assertSuccessful()
        ->assertJsonPath('data.status', 'active')
        ->assertJsonPath('data.is_active', true)
        ->assertJsonPath('data.progress_percentage', 45)
        ->assertJsonPath('data.course.slug', 'test-course');
});

it('returns expired status when enrollment is expired', function (): void {
    $tenant = makeTenant(['domain' => 'tenant-a.local']);
    [$student, $headers] = actingAsUserType(UserType::Student, $tenant);

    $course = Course::query()->create([
        'tenant_id' => $tenant->id,
        'title' => 'Expired Course',
        'slug' => 'expired-course',
        'description' => 'Course description',
        'status' => 'published',
        'price_cents' => 1000,
        'access_days' => 30,
        'is_featured' => false,
    ]);

    Enrollment::query()->create([
        'tenant_id' => $tenant->id,
        'user_id' => $student->id,
        'course_id' => $course->id,
        'status' => 'expired',
        'enrolled_at' => now()->subDays(60),
        'access_expires_at' => now()->subDay(),
        'progress_percentage' => 30,
    ]);

    $this->getJson("/api/v1/learning/courses/{$course->id}/enrollment", $headers)
        ->assertSuccessful()
        ->assertJsonPath('data.status', 'expired')
        ->assertJsonPath('data.is_active', false);
});

it('prefers the current enrollment over historical rows', function (): void {
    $tenant = makeTenant(['domain' => 'tenant-a.local']);
    [$student, $headers] = actingAsUserType(UserType::Student, $tenant);

    $course = Course::query()->create([
        'tenant_id' => $tenant->id,
        'title' => 'Current Course',
        'slug' => 'current-course',
        'description' => 'Course description',
        'status' => 'published',
        'price_cents' => 1000,
        'access_days' => 30,
        'is_featured' => false,
    ]);

    Enrollment::query()->create([
        'tenant_id' => $tenant->id,
        'user_id' => $student->id,
        'course_id' => $course->id,
        'status' => 'cancelled',
        'enrolled_at' => now()->subDays(20),
        'progress_percentage' => 10,
    ]);

    $current = Enrollment::query()->create([
        'tenant_id' => $tenant->id,
        'user_id' => $student->id,
        'course_id' => $course->id,
        'status' => 'active',
        'enrolled_at' => now()->subDay(),
        'access_expires_at' => now()->addDays(30),
        'progress_percentage' => 55,
    ]);

    $this->getJson("/api/v1/learning/courses/{$course->id}/enrollment", $headers)
        ->assertSuccessful()
        ->assertJsonPath('data.id', $current->id)
        ->assertJsonPath('data.status', 'active')
        ->assertJsonPath('data.progress_percentage', 55);
});

it('falls back to the latest historical enrollment when no current enrollment exists', function (): void {
    $tenant = makeTenant(['domain' => 'tenant-a.local']);
    [$student, $headers] = actingAsUserType(UserType::Student, $tenant);

    $course = Course::query()->create([
        'tenant_id' => $tenant->id,
        'title' => 'Historical Course',
        'slug' => 'historical-course',
        'description' => 'Course description',
        'status' => 'published',
        'price_cents' => 1000,
        'access_days' => 30,
        'is_featured' => false,
    ]);

    Enrollment::query()->create([
        'tenant_id' => $tenant->id,
        'user_id' => $student->id,
        'course_id' => $course->id,
        'status' => 'cancelled',
        'enrolled_at' => now()->subDays(20),
        'progress_percentage' => 10,
    ]);

    $latestHistorical = Enrollment::query()->create([
        'tenant_id' => $tenant->id,
        'user_id' => $student->id,
        'course_id' => $course->id,
        'status' => 'expired',
        'enrolled_at' => now()->subDay(),
        'access_expires_at' => now()->subDay(),
        'progress_percentage' => 40,
    ]);

    $this->getJson("/api/v1/learning/courses/{$course->id}/enrollment", $headers)
        ->assertSuccessful()
        ->assertJsonPath('data.id', $latestHistorical->id)
        ->assertJsonPath('data.status', 'expired')
        ->assertJsonPath('data.progress_percentage', 40);
});

it('requires authentication to view enrollment', function (): void {
    $tenant = makeTenant(['domain' => 'tenant-a.local']);

    $course = Course::query()->create([
        'tenant_id' => $tenant->id,
        'title' => 'Test Course',
        'slug' => 'test-course',
        'description' => 'Course description',
        'status' => 'published',
        'price_cents' => 0,
        'access_days' => 30,
        'is_featured' => false,
    ]);

    $this->getJson("/api/v1/learning/courses/{$course->id}/enrollment", tenantHeaders($tenant))
        ->assertUnauthorized();
});

it('requires authentication to create enrollment', function (): void {
    $tenant = makeTenant(['domain' => 'tenant-a.local']);

    $course = Course::query()->create([
        'tenant_id' => $tenant->id,
        'title' => 'Test Course',
        'slug' => 'test-course',
        'description' => 'Course description',
        'status' => 'published',
        'price_cents' => 1000,
        'access_days' => 30,
        'is_featured' => false,
    ]);

    $this->postJson('/api/v1/learning/enrollments', [
        'course_id' => $course->id,
    ], tenantHeaders($tenant))
        ->assertUnauthorized();
});

it('forbids student creating enrollment for another user', function (): void {
    $tenant = makeTenant(['domain' => 'tenant-a.local']);
    [$student, $headers] = actingAsUserType(UserType::Student, $tenant);

    $course = Course::query()->create([
        'tenant_id' => $tenant->id,
        'title' => 'Test Course',
        'slug' => 'test-course',
        'description' => 'Course description',
        'status' => 'published',
        'price_cents' => 1000,
        'access_days' => 30,
        'is_featured' => false,
    ]);

    $otherUser = User::factory()->forTenant($tenant)->student()->create();

    $this->postJson('/api/v1/learning/enrollments', [
        'course_id' => $course->id,
        'user_id' => $otherUser->id,
    ], $headers)
        ->assertForbidden();
});

it('rejects duplicate current enrollment gracefully', function (string $status): void {
    $tenant = makeTenant(['domain' => 'tenant-a.local']);
    [$admin, $headers] = actingAsUserType(UserType::Admin, $tenant);

    $course = Course::query()->create([
        'tenant_id' => $tenant->id,
        'title' => 'Test Course',
        'slug' => 'test-course',
        'description' => 'Course description',
        'status' => 'published',
        'price_cents' => 1000,
        'access_days' => 30,
        'is_featured' => false,
    ]);

    Enrollment::query()->create([
        'tenant_id' => $tenant->id,
        'user_id' => $admin->id,
        'course_id' => $course->id,
        'status' => $status,
        'enrolled_at' => now(),
        'access_expires_at' => now()->addDays(30),
        'progress_percentage' => 0,
    ]);

    $this->postJson('/api/v1/learning/enrollments', [
        'course_id' => $course->id,
    ], $headers)
        ->assertStatus(422)
        ->assertJsonPath('errors.0.code', 'validation_error');
})->with(['active', 'pending']);

it('allows re-enrollment after cancelled enrollment', function (): void {
    $tenant = makeTenant(['domain' => 'tenant-a.local']);
    [$admin, $headers] = actingAsUserType(UserType::Admin, $tenant);

    $course = Course::query()->create([
        'tenant_id' => $tenant->id,
        'title' => 'Reenroll Course',
        'slug' => 'reenroll-course',
        'description' => 'Course description',
        'status' => 'published',
        'price_cents' => 1000,
        'access_days' => 30,
        'is_featured' => false,
    ]);

    Enrollment::query()->create([
        'tenant_id' => $tenant->id,
        'user_id' => $admin->id,
        'course_id' => $course->id,
        'status' => 'cancelled',
        'enrolled_at' => now()->subDays(10),
        'progress_percentage' => 80,
    ]);

    $this->postJson('/api/v1/learning/enrollments', [
        'course_id' => $course->id,
    ], $headers)
        ->assertCreated()
        ->assertJsonPath('data.status', 'active');

    expect(Enrollment::query()->where('tenant_id', $tenant->id)->where('user_id', $admin->id)->where('course_id', $course->id)->count())->toBe(2);
});

it('allows re-enrollment after expired enrollment', function (): void {
    $tenant = makeTenant(['domain' => 'tenant-a.local']);
    [$admin, $headers] = actingAsUserType(UserType::Admin, $tenant);

    $course = Course::query()->create([
        'tenant_id' => $tenant->id,
        'title' => 'Reenroll Expired Course',
        'slug' => 'reenroll-expired-course',
        'description' => 'Course description',
        'status' => 'published',
        'price_cents' => 1000,
        'access_days' => 30,
        'is_featured' => false,
    ]);

    Enrollment::query()->create([
        'tenant_id' => $tenant->id,
        'user_id' => $admin->id,
        'course_id' => $course->id,
        'status' => 'expired',
        'enrolled_at' => now()->subDays(40),
        'access_expires_at' => now()->subDay(),
        'progress_percentage' => 100,
    ]);

    $this->postJson('/api/v1/learning/enrollments', [
        'course_id' => $course->id,
    ], $headers)
        ->assertCreated()
        ->assertJsonPath('data.status', 'active');

    expect(Enrollment::query()->where('tenant_id', $tenant->id)->where('user_id', $admin->id)->where('course_id', $course->id)->count())->toBe(2);
});

it('enforces tenant isolation for user and course', function (): void {
    $tenantA = makeTenant(['domain' => 'tenant-a.local']);
    $tenantB = makeTenant(['domain' => 'tenant-b.local']);
    [$admin, $headers] = actingAsUserType(UserType::Admin, $tenantA);

    $course = Course::query()->create([
        'tenant_id' => $tenantB->id,
        'title' => 'Foreign Course',
        'slug' => 'foreign-course',
        'description' => 'Course description',
        'status' => 'published',
        'price_cents' => 1000,
        'access_days' => 30,
        'is_featured' => false,
    ]);

    $foreignUser = User::factory()->forTenant($tenantB)->student()->create();

    $this->postJson('/api/v1/learning/enrollments', [
        'course_id' => $course->id,
        'user_id' => $foreignUser->id,
    ], $headers)
        ->assertNotFound();
});

it('lists enrollments as admin with filters and resource shape', function (): void {
    $tenant = makeTenant(['domain' => 'tenant-a.local']);
    [, $headers] = actingAsUserType(UserType::Admin, $tenant);

    $courseA = Course::query()->create([
        'tenant_id' => $tenant->id,
        'title' => 'Course A',
        'slug' => 'course-a',
        'description' => 'Course description',
        'status' => 'published',
        'price_cents' => 1000,
        'access_days' => 30,
        'is_featured' => false,
    ]);

    $courseB = Course::query()->create([
        'tenant_id' => $tenant->id,
        'title' => 'Course B',
        'slug' => 'course-b',
        'description' => 'Course description',
        'status' => 'published',
        'price_cents' => 1000,
        'access_days' => 30,
        'is_featured' => false,
    ]);

    $student = User::factory()->forTenant($tenant)->student()->create();

    $matching = Enrollment::query()->create([
        'tenant_id' => $tenant->id,
        'user_id' => $student->id,
        'course_id' => $courseA->id,
        'status' => 'active',
        'enrolled_at' => now(),
        'progress_percentage' => 20,
    ]);

    Enrollment::query()->create([
        'tenant_id' => $tenant->id,
        'user_id' => $student->id,
        'course_id' => $courseB->id,
        'status' => 'expired',
        'enrolled_at' => now(),
        'access_expires_at' => now()->subDay(),
        'progress_percentage' => 100,
    ]);

    $this->getJson('/api/v1/learning/enrollments?status=active&course_id='.$courseA->id.'&user_id='.$student->id, $headers)
        ->assertOk()
        ->assertJsonPath('data.0.id', $matching->id)
        ->assertJsonPath('data.0.user_id', $student->id)
        ->assertJsonPath('data.0.course_id', $courseA->id)
        ->assertJsonPath('data.0.course.slug', 'course-a')
        ->assertJsonPath('data.0.user.id', $student->id);
});

it('shows enrollment by id as admin', function (): void {
    $tenant = makeTenant(['domain' => 'tenant-a.local']);
    [, $headers] = actingAsUserType(UserType::Admin, $tenant);

    $course = Course::query()->create([
        'tenant_id' => $tenant->id,
        'title' => 'Test Course',
        'slug' => 'test-course',
        'description' => 'Course description',
        'status' => 'published',
        'price_cents' => 1000,
        'access_days' => 30,
        'is_featured' => false,
    ]);

    $student = User::factory()->forTenant($tenant)->student()->create();
    $enrollment = Enrollment::query()->create([
        'tenant_id' => $tenant->id,
        'user_id' => $student->id,
        'course_id' => $course->id,
        'status' => 'active',
        'enrolled_at' => now(),
        'progress_percentage' => 10,
    ]);

    $this->getJson("/api/v1/learning/enrollments/{$enrollment->id}", $headers)
        ->assertOk()
        ->assertJsonPath('data.id', $enrollment->id)
        ->assertJsonPath('data.user_id', $student->id)
        ->assertJsonPath('data.user.id', $student->id);
});

it('allows student to view own enrollment by id but not another student enrollment', function (): void {
    $tenant = makeTenant(['domain' => 'tenant-a.local']);
    [$student, $headers] = actingAsUserType(UserType::Student, $tenant);

    $course = Course::query()->create([
        'tenant_id' => $tenant->id,
        'title' => 'Test Course',
        'slug' => 'test-course',
        'description' => 'Course description',
        'status' => 'published',
        'price_cents' => 1000,
        'access_days' => 30,
        'is_featured' => false,
    ]);

    $ownEnrollment = Enrollment::query()->create([
        'tenant_id' => $tenant->id,
        'user_id' => $student->id,
        'course_id' => $course->id,
        'status' => 'active',
        'enrolled_at' => now(),
        'progress_percentage' => 10,
    ]);

    $otherStudent = User::factory()->forTenant($tenant)->student()->create();
    $otherEnrollment = Enrollment::query()->create([
        'tenant_id' => $tenant->id,
        'user_id' => $otherStudent->id,
        'course_id' => $course->id,
        'status' => 'active',
        'enrolled_at' => now(),
        'progress_percentage' => 5,
    ]);

    $this->getJson("/api/v1/learning/enrollments/{$ownEnrollment->id}", $headers)
        ->assertOk()
        ->assertJsonPath('data.id', $ownEnrollment->id);

    $this->getJson("/api/v1/learning/enrollments/{$otherEnrollment->id}", $headers)
        ->assertForbidden();
});

it('updates enrollment as admin without changing lifecycle at full progress', function (): void {
    $tenant = makeTenant(['domain' => 'tenant-a.local']);
    [, $headers] = actingAsUserType(UserType::Admin, $tenant);

    $course = Course::query()->create([
        'tenant_id' => $tenant->id,
        'title' => 'Test Course',
        'slug' => 'test-course',
        'description' => 'Course description',
        'status' => 'published',
        'price_cents' => 1000,
        'access_days' => 30,
        'is_featured' => false,
    ]);

    $student = User::factory()->forTenant($tenant)->student()->create();
    $enrollment = Enrollment::query()->create([
        'tenant_id' => $tenant->id,
        'user_id' => $student->id,
        'course_id' => $course->id,
        'status' => 'active',
        'enrolled_at' => now(),
        'progress_percentage' => 10,
    ]);

    $this->patchJson("/api/v1/learning/enrollments/{$enrollment->id}", [
        'progress_percentage' => 85,
        'access_expires_at' => now()->addDays(10)->toDateString(),
    ], $headers)
        ->assertOk()
        ->assertJsonPath('data.status', 'active')
        ->assertJsonPath('data.progress_percentage', 85)
        ->assertJsonPath('data.access_expires_at', now()->addDays(10)->startOfDay()->toISOString());
});

it('rejects completed status on enrollment update', function (): void {
    $tenant = makeTenant(['domain' => 'tenant-a.local']);
    [, $headers] = actingAsUserType(UserType::Admin, $tenant);

    $course = Course::query()->create([
        'tenant_id' => $tenant->id,
        'title' => 'Test Course',
        'slug' => 'test-course',
        'description' => 'Course description',
        'status' => 'published',
        'price_cents' => 1000,
        'access_days' => 30,
        'is_featured' => false,
    ]);

    $student = User::factory()->forTenant($tenant)->student()->create();
    $enrollment = Enrollment::query()->create([
        'tenant_id' => $tenant->id,
        'user_id' => $student->id,
        'course_id' => $course->id,
        'status' => 'active',
        'enrolled_at' => now(),
        'progress_percentage' => 10,
    ]);

    $this->patchJson("/api/v1/learning/enrollments/{$enrollment->id}", [
        'status' => 'completed',
    ], $headers)
        ->assertUnprocessable()
        ->assertJsonPath('errors.0.code', 'validation_error');
});

it('keeps enrollment active when progress reaches 100', function (): void {
    $tenant = makeTenant(['domain' => 'tenant-a.local']);
    [, $headers] = actingAsUserType(UserType::Admin, $tenant);

    $course = Course::query()->create([
        'tenant_id' => $tenant->id,
        'title' => 'Test Course',
        'slug' => 'test-course',
        'description' => 'Course description',
        'status' => 'published',
        'price_cents' => 1000,
        'access_days' => 30,
        'is_featured' => false,
    ]);

    $student = User::factory()->forTenant($tenant)->student()->create();
    $enrollment = Enrollment::query()->create([
        'tenant_id' => $tenant->id,
        'user_id' => $student->id,
        'course_id' => $course->id,
        'status' => 'active',
        'enrolled_at' => now(),
        'progress_percentage' => 90,
    ]);

    $this->patchJson("/api/v1/learning/enrollments/{$enrollment->id}", [
        'progress_percentage' => 100,
    ], $headers)
        ->assertOk()
        ->assertJsonPath('data.status', 'active')
        ->assertJsonPath('data.progress_percentage', 100);
});

it('cancels enrollment logically', function (): void {
    $tenant = makeTenant(['domain' => 'tenant-a.local']);
    [, $headers] = actingAsUserType(UserType::Admin, $tenant);

    $course = Course::query()->create([
        'tenant_id' => $tenant->id,
        'title' => 'Test Course',
        'slug' => 'test-course',
        'description' => 'Course description',
        'status' => 'published',
        'price_cents' => 1000,
        'access_days' => 30,
        'is_featured' => false,
    ]);

    $student = User::factory()->forTenant($tenant)->student()->create();
    $enrollment = Enrollment::query()->create([
        'tenant_id' => $tenant->id,
        'user_id' => $student->id,
        'course_id' => $course->id,
        'status' => 'active',
        'enrolled_at' => now(),
        'progress_percentage' => 10,
        'completed_at' => now()->subDay(),
    ]);
    $completedAt = $enrollment->completed_at?->toDateTimeString();

    $this->withHeaders($headers)->deleteJson("/api/v1/learning/enrollments/{$enrollment->id}")
        ->assertOk()
        ->assertJsonPath('message', 'Enrollment cancelled successfully.');

    $enrollment->refresh();
    expect($enrollment->status)->toBe('cancelled');
    expect($enrollment->progress_percentage)->toBe(10);
    expect($enrollment->completed_at?->toDateTimeString())->toBe($completedAt);
});

it('requires authentication for protected enrollment routes', function (): void {
    $tenant = makeTenant(['domain' => 'tenant-a.local']);
    $course = Course::query()->create([
        'tenant_id' => $tenant->id,
        'title' => 'Test Course',
        'slug' => 'test-course',
        'description' => 'Course description',
        'status' => 'published',
        'price_cents' => 1000,
        'access_days' => 30,
        'is_featured' => false,
    ]);

    $this->getJson('/api/v1/learning/enrollments', tenantHeaders($tenant))->assertUnauthorized();
    $this->getJson('/api/v1/learning/enrollments/1', tenantHeaders($tenant))->assertUnauthorized();
    $this->patchJson('/api/v1/learning/enrollments/1', [], tenantHeaders($tenant))->assertUnauthorized();
    $this->deleteJson('/api/v1/learning/enrollments/1', tenantHeaders($tenant))->assertUnauthorized();
});

it('forbids student from listing updating or deleting enrollments', function (): void {
    $tenant = makeTenant(['domain' => 'tenant-a.local']);
    [$student, $headers] = actingAsUserType(UserType::Student, $tenant);

    $course = Course::query()->create([
        'tenant_id' => $tenant->id,
        'title' => 'Test Course',
        'slug' => 'test-course',
        'description' => 'Course description',
        'status' => 'published',
        'price_cents' => 1000,
        'access_days' => 30,
        'is_featured' => false,
    ]);

    $enrollment = Enrollment::query()->create([
        'tenant_id' => $tenant->id,
        'user_id' => $student->id,
        'course_id' => $course->id,
        'status' => 'active',
        'enrolled_at' => now(),
        'progress_percentage' => 10,
    ]);

    $this->getJson('/api/v1/learning/enrollments', $headers)->assertForbidden();
    $this->patchJson("/api/v1/learning/enrollments/{$enrollment->id}", ['status' => 'active'], $headers)->assertForbidden();
    $this->withHeaders($headers)->deleteJson("/api/v1/learning/enrollments/{$enrollment->id}")->assertForbidden();
});

it('returns not found for cross tenant enrollment actions', function (): void {
    $tenantA = makeTenant(['domain' => 'tenant-a.local']);
    $tenantB = makeTenant(['domain' => 'tenant-b.local']);
    [, $headers] = actingAsUserType(UserType::Admin, $tenantA);

    $course = Course::query()->create([
        'tenant_id' => $tenantB->id,
        'title' => 'Foreign Course',
        'slug' => 'foreign-course',
        'description' => 'Course description',
        'status' => 'published',
        'price_cents' => 1000,
        'access_days' => 30,
        'is_featured' => false,
    ]);

    $foreignUser = User::factory()->forTenant($tenantB)->student()->create();
    $enrollment = Enrollment::query()->create([
        'tenant_id' => $tenantB->id,
        'user_id' => $foreignUser->id,
        'course_id' => $course->id,
        'status' => 'active',
        'enrolled_at' => now(),
        'progress_percentage' => 10,
    ]);

    $this->getJson("/api/v1/learning/enrollments/{$enrollment->id}", $headers)->assertNotFound();
    $this->patchJson("/api/v1/learning/enrollments/{$enrollment->id}", ['status' => 'active'], $headers)->assertNotFound();
    $this->withHeaders($headers)->deleteJson("/api/v1/learning/enrollments/{$enrollment->id}")->assertNotFound();
});
