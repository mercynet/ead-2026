<?php

use App\Modules\Core\Enums\UserType;
use App\Modules\Learning\Models\Course;
use App\Modules\Learning\Models\CourseModule;

it('lets an instructor create a module in the current tenant and appends sort order', function (): void {
    $tenant = makeTenant(['domain' => 'tenant-a.local']);
    [$instructor, $headers] = actingAsUserType(UserType::Instructor, $tenant);

    $course = Course::query()->create([
        'tenant_id' => $tenant->id,
        'instructor_id' => $instructor->id,
        'title' => 'Course A',
        'slug' => 'course-a',
        'description' => 'Course description',
        'status' => 'draft',
        'price_cents' => 0,
        'access_days' => 30,
        'is_featured' => false,
    ]);

    CourseModule::query()->create([
        'tenant_id' => $tenant->id,
        'course_id' => $course->id,
        'title' => 'Existing module',
        'sort_order' => 1,
    ]);

    $this->postJson('/api/v1/learning/modules', [
        'course_id' => $course->id,
        'title' => 'New module',
    ], $headers)
        ->assertCreated()
        ->assertJsonPath('data.course_id', $course->id)
        ->assertJsonPath('data.title', 'New module')
        ->assertJsonPath('data.sort_order', 2);

    $module = CourseModule::query()->where('title', 'New module')->first();

    expect($module)->not->toBeNull();
    expect($module?->tenant_id)->toBe($tenant->id);
    expect($module?->sort_order)->toBe(2);
});

it('lets an authorized user view a module in the current tenant', function (): void {
    $tenant = makeTenant(['domain' => 'tenant-a.local']);
    [$instructor, $headers] = actingAsUserType(UserType::Instructor, $tenant);

    $course = Course::query()->create([
        'tenant_id' => $tenant->id,
        'instructor_id' => $instructor->id,
        'title' => 'Course A',
        'slug' => 'course-a',
        'description' => 'Course description',
        'status' => 'draft',
        'price_cents' => 0,
        'access_days' => 30,
        'is_featured' => false,
    ]);

    $module = CourseModule::query()->create([
        'tenant_id' => $tenant->id,
        'course_id' => $course->id,
        'title' => 'Module A',
        'sort_order' => 1,
    ]);

    $this->getJson('/api/v1/learning/modules/'.$module->id, $headers)
        ->assertOk()
        ->assertJsonPath('data.id', $module->id)
        ->assertJsonPath('data.course_id', $course->id)
        ->assertJsonPath('data.title', 'Module A')
        ->assertJsonPath('data.sort_order', 1);
});

it('returns 403 for a student without permission when viewing a module', function (): void {
    $tenant = makeTenant(['domain' => 'tenant-a.local']);
    [, $headers] = actingAsUserType(UserType::Student, $tenant);

    $course = Course::query()->create([
        'tenant_id' => $tenant->id,
        'title' => 'Course A',
        'slug' => 'course-a',
        'description' => 'Course description',
        'status' => 'draft',
        'price_cents' => 0,
        'access_days' => 30,
        'is_featured' => false,
    ]);

    $module = CourseModule::query()->create([
        'tenant_id' => $tenant->id,
        'course_id' => $course->id,
        'title' => 'Module A',
        'sort_order' => 1,
    ]);

    $this->getJson('/api/v1/learning/modules/'.$module->id, $headers)
        ->assertForbidden();
});

it('returns 404 for a missing module or one from another tenant', function (): void {
    $tenantA = makeTenant(['domain' => 'tenant-a.local']);
    $tenantB = makeTenant(['domain' => 'tenant-b.local']);
    [, $headers] = actingAsUserType(UserType::Admin, $tenantA);

    $course = Course::query()->create([
        'tenant_id' => $tenantB->id,
        'title' => 'Course B',
        'slug' => 'course-b',
        'description' => 'Course description',
        'status' => 'draft',
        'price_cents' => 0,
        'access_days' => 30,
        'is_featured' => false,
    ]);

    $foreignModule = CourseModule::query()->create([
        'tenant_id' => $tenantB->id,
        'course_id' => $course->id,
        'title' => 'Foreign module',
        'sort_order' => 1,
    ]);

    $this->getJson('/api/v1/learning/modules/999999', $headers)
        ->assertNotFound();

    $this->getJson('/api/v1/learning/modules/'.$foreignModule->id, $headers)
        ->assertNotFound();
});

it('returns 401 without authentication when viewing a module', function (): void {
    $tenant = makeTenant(['domain' => 'tenant-a.local']);
    $course = Course::query()->create([
        'tenant_id' => $tenant->id,
        'title' => 'Course A',
        'slug' => 'course-a',
        'description' => 'Course description',
        'status' => 'draft',
        'price_cents' => 0,
        'access_days' => 30,
        'is_featured' => false,
    ]);

    $module = CourseModule::query()->create([
        'tenant_id' => $tenant->id,
        'course_id' => $course->id,
        'title' => 'Module A',
        'sort_order' => 1,
    ]);

    $this->getJson('/api/v1/learning/modules/'.$module->id, tenantHeaders($tenant))
        ->assertUnauthorized();
});

it('updates a module title for an authorized user in the current tenant', function (): void {
    $tenant = makeTenant(['domain' => 'tenant-a.local']);
    [$instructor, $headers] = actingAsUserType(UserType::Instructor, $tenant);

    $course = Course::query()->create([
        'tenant_id' => $tenant->id,
        'instructor_id' => $instructor->id,
        'title' => 'Course A',
        'slug' => 'course-a',
        'description' => 'Course description',
        'status' => 'draft',
        'price_cents' => 0,
        'access_days' => 30,
        'is_featured' => false,
    ]);

    $module = CourseModule::query()->create([
        'tenant_id' => $tenant->id,
        'course_id' => $course->id,
        'title' => 'Old title',
        'sort_order' => 1,
    ]);

    $this->patchJson('/api/v1/learning/modules/'.$module->id, [
        'title' => 'Updated title',
    ], $headers)
        ->assertOk()
        ->assertJsonPath('data.id', $module->id)
        ->assertJsonPath('data.course_id', $course->id)
        ->assertJsonPath('data.title', 'Updated title')
        ->assertJsonPath('data.sort_order', 1);

    expect($module->refresh()->title)->toBe('Updated title');
});

it('returns 403 for a student without permission when updating a module', function (): void {
    $tenant = makeTenant(['domain' => 'tenant-a.local']);
    [, $headers] = actingAsUserType(UserType::Student, $tenant);

    $course = Course::query()->create([
        'tenant_id' => $tenant->id,
        'title' => 'Course A',
        'slug' => 'course-a',
        'description' => 'Course description',
        'status' => 'draft',
        'price_cents' => 0,
        'access_days' => 30,
        'is_featured' => false,
    ]);

    $module = CourseModule::query()->create([
        'tenant_id' => $tenant->id,
        'course_id' => $course->id,
        'title' => 'Old title',
        'sort_order' => 1,
    ]);

    $this->patchJson('/api/v1/learning/modules/'.$module->id, [
        'title' => 'Updated title',
    ], $headers)
        ->assertForbidden();
});

it('returns 404 for a missing or foreign module when updating', function (): void {
    $tenantA = makeTenant(['domain' => 'tenant-a.local']);
    $tenantB = makeTenant(['domain' => 'tenant-b.local']);
    [, $headers] = actingAsUserType(UserType::Admin, $tenantA);

    $course = Course::query()->create([
        'tenant_id' => $tenantB->id,
        'title' => 'Course B',
        'slug' => 'course-b',
        'description' => 'Course description',
        'status' => 'draft',
        'price_cents' => 0,
        'access_days' => 30,
        'is_featured' => false,
    ]);

    $foreignModule = CourseModule::query()->create([
        'tenant_id' => $tenantB->id,
        'course_id' => $course->id,
        'title' => 'Foreign module',
        'sort_order' => 1,
    ]);

    $this->patchJson('/api/v1/learning/modules/999999', [
        'title' => 'Updated title',
    ], $headers)->assertNotFound();

    $this->patchJson('/api/v1/learning/modules/'.$foreignModule->id, [
        'title' => 'Updated title',
    ], $headers)->assertNotFound();
});

it('returns 422 for invalid module update payload', function (): void {
    $tenant = makeTenant(['domain' => 'tenant-a.local']);
    [, $headers] = actingAsUserType(UserType::Admin, $tenant);

    $course = Course::query()->create([
        'tenant_id' => $tenant->id,
        'title' => 'Course A',
        'slug' => 'course-a',
        'description' => 'Course description',
        'status' => 'draft',
        'price_cents' => 0,
        'access_days' => 30,
        'is_featured' => false,
    ]);

    $module = CourseModule::query()->create([
        'tenant_id' => $tenant->id,
        'course_id' => $course->id,
        'title' => 'Old title',
        'sort_order' => 1,
    ]);

    $this->patchJson('/api/v1/learning/modules/'.$module->id, [], $headers)
        ->assertStatus(422)
        ->assertJsonPath('errors.0.code', 'validation_error');
});

it('deletes a module for an authorized user in the current tenant', function (): void {
    $tenant = makeTenant(['domain' => 'tenant-a.local']);
    [$instructor, $headers] = actingAsUserType(UserType::Instructor, $tenant);

    $course = Course::query()->create([
        'tenant_id' => $tenant->id,
        'instructor_id' => $instructor->id,
        'title' => 'Course A',
        'slug' => 'course-a',
        'description' => 'Course description',
        'status' => 'draft',
        'price_cents' => 0,
        'access_days' => 30,
        'is_featured' => false,
    ]);

    $module = CourseModule::query()->create([
        'tenant_id' => $tenant->id,
        'course_id' => $course->id,
        'title' => 'Module to delete',
        'sort_order' => 1,
    ]);

    $this->deleteJson('/api/v1/learning/modules/'.$module->id, [], $headers)
        ->assertOk()
        ->assertJsonPath('message', 'Module deleted successfully.');

    $this->assertDatabaseMissing('course_modules', ['id' => $module->id]);
});

it('returns 403 for a student without permission when deleting a module', function (): void {
    $tenant = makeTenant(['domain' => 'tenant-a.local']);
    [, $headers] = actingAsUserType(UserType::Student, $tenant);

    $course = Course::query()->create([
        'tenant_id' => $tenant->id,
        'title' => 'Course A',
        'slug' => 'course-a',
        'description' => 'Course description',
        'status' => 'draft',
        'price_cents' => 0,
        'access_days' => 30,
        'is_featured' => false,
    ]);

    $module = CourseModule::query()->create([
        'tenant_id' => $tenant->id,
        'course_id' => $course->id,
        'title' => 'Module to delete',
        'sort_order' => 1,
    ]);

    $this->deleteJson('/api/v1/learning/modules/'.$module->id, [], $headers)
        ->assertForbidden();
});

it('returns 404 for a missing or foreign module when deleting', function (): void {
    $tenantA = makeTenant(['domain' => 'tenant-a.local']);
    $tenantB = makeTenant(['domain' => 'tenant-b.local']);
    [, $headers] = actingAsUserType(UserType::Admin, $tenantA);

    $course = Course::query()->create([
        'tenant_id' => $tenantB->id,
        'title' => 'Course B',
        'slug' => 'course-b',
        'description' => 'Course description',
        'status' => 'draft',
        'price_cents' => 0,
        'access_days' => 30,
        'is_featured' => false,
    ]);

    $foreignModule = CourseModule::query()->create([
        'tenant_id' => $tenantB->id,
        'course_id' => $course->id,
        'title' => 'Foreign module',
        'sort_order' => 1,
    ]);

    $this->deleteJson('/api/v1/learning/modules/999999', [], $headers)
        ->assertNotFound();

    $this->deleteJson('/api/v1/learning/modules/'.$foreignModule->id, [], $headers)
        ->assertNotFound();
});

it('returns 401 without authentication when deleting a module', function (): void {
    $tenant = makeTenant(['domain' => 'tenant-a.local']);
    $course = Course::query()->create([
        'tenant_id' => $tenant->id,
        'title' => 'Course A',
        'slug' => 'course-a',
        'description' => 'Course description',
        'status' => 'draft',
        'price_cents' => 0,
        'access_days' => 30,
        'is_featured' => false,
    ]);

    $module = CourseModule::query()->create([
        'tenant_id' => $tenant->id,
        'course_id' => $course->id,
        'title' => 'Module to delete',
        'sort_order' => 1,
    ]);

    $this->deleteJson('/api/v1/learning/modules/'.$module->id, [], tenantHeaders($tenant))
        ->assertUnauthorized();
});

it('reorders all modules for an authorized user in the current tenant', function (): void {
    $tenant = makeTenant(['domain' => 'tenant-a.local']);
    [$instructor, $headers] = actingAsUserType(UserType::Instructor, $tenant);

    $course = Course::query()->create([
        'tenant_id' => $tenant->id,
        'instructor_id' => $instructor->id,
        'title' => 'Course A',
        'slug' => 'course-a',
        'description' => 'Course description',
        'status' => 'draft',
        'price_cents' => 0,
        'access_days' => 30,
        'is_featured' => false,
    ]);

    $moduleA = CourseModule::query()->create([
        'tenant_id' => $tenant->id,
        'course_id' => $course->id,
        'title' => 'Module A',
        'sort_order' => 1,
    ]);

    $moduleB = CourseModule::query()->create([
        'tenant_id' => $tenant->id,
        'course_id' => $course->id,
        'title' => 'Module B',
        'sort_order' => 2,
    ]);

    $moduleC = CourseModule::query()->create([
        'tenant_id' => $tenant->id,
        'course_id' => $course->id,
        'title' => 'Module C',
        'sort_order' => 3,
    ]);

    $this->patchJson('/api/v1/learning/modules/reorder', [
        'course_id' => $course->id,
        'module_ids' => [$moduleC->id, $moduleA->id, $moduleB->id],
    ], $headers)
        ->assertOk()
        ->assertJsonPath('data.0.id', $moduleC->id)
        ->assertJsonPath('data.0.sort_order', 1)
        ->assertJsonPath('data.1.id', $moduleA->id)
        ->assertJsonPath('data.1.sort_order', 2)
        ->assertJsonPath('data.2.id', $moduleB->id)
        ->assertJsonPath('data.2.sort_order', 3);

    expect($moduleA->refresh()->sort_order)->toBe(2);
    expect($moduleB->refresh()->sort_order)->toBe(3);
    expect($moduleC->refresh()->sort_order)->toBe(1);
});

it('returns 403 for a student without permission when reordering modules', function (): void {
    $tenant = makeTenant(['domain' => 'tenant-a.local']);
    [, $headers] = actingAsUserType(UserType::Student, $tenant);

    $course = Course::query()->create([
        'tenant_id' => $tenant->id,
        'title' => 'Course A',
        'slug' => 'course-a',
        'description' => 'Course description',
        'status' => 'draft',
        'price_cents' => 0,
        'access_days' => 30,
        'is_featured' => false,
    ]);

    $module = CourseModule::query()->create([
        'tenant_id' => $tenant->id,
        'course_id' => $course->id,
        'title' => 'Module A',
        'sort_order' => 1,
    ]);

    $this->patchJson('/api/v1/learning/modules/reorder', [
        'course_id' => $course->id,
        'module_ids' => [$module->id],
    ], $headers)
        ->assertForbidden();
});

it('returns 401 without authentication when reordering modules', function (): void {
    $tenant = makeTenant(['domain' => 'tenant-a.local']);
    $course = Course::query()->create([
        'tenant_id' => $tenant->id,
        'title' => 'Course A',
        'slug' => 'course-a',
        'description' => 'Course description',
        'status' => 'draft',
        'price_cents' => 0,
        'access_days' => 30,
        'is_featured' => false,
    ]);

    $this->patchJson('/api/v1/learning/modules/reorder', [
        'course_id' => $course->id,
        'module_ids' => [],
    ], tenantHeaders($tenant))
        ->assertUnauthorized();
});

it('returns 422 for invalid reorder payloads', function (): void {
    $tenant = makeTenant(['domain' => 'tenant-a.local']);
    [, $headers] = actingAsUserType(UserType::Admin, $tenant);
    $otherTenant = makeTenant(['domain' => 'tenant-b.local']);

    $course = Course::query()->create([
        'tenant_id' => $tenant->id,
        'title' => 'Course A',
        'slug' => 'course-a',
        'description' => 'Course description',
        'status' => 'draft',
        'price_cents' => 0,
        'access_days' => 30,
        'is_featured' => false,
    ]);

    $foreignCourse = Course::query()->create([
        'tenant_id' => $otherTenant->id,
        'title' => 'Foreign course',
        'slug' => 'foreign-course',
        'description' => 'Course description',
        'status' => 'draft',
        'price_cents' => 0,
        'access_days' => 30,
        'is_featured' => false,
    ]);

    $moduleA = CourseModule::query()->create([
        'tenant_id' => $tenant->id,
        'course_id' => $course->id,
        'title' => 'Module A',
        'sort_order' => 1,
    ]);

    $moduleB = CourseModule::query()->create([
        'tenant_id' => $tenant->id,
        'course_id' => $course->id,
        'title' => 'Module B',
        'sort_order' => 2,
    ]);

    $foreignModule = CourseModule::query()->create([
        'tenant_id' => $otherTenant->id,
        'course_id' => $foreignCourse->id,
        'title' => 'Foreign module',
        'sort_order' => 1,
    ]);

    $this->patchJson('/api/v1/learning/modules/reorder', [
        'course_id' => $foreignCourse->id,
        'module_ids' => [$foreignModule->id],
    ], $headers)
        ->assertStatus(422)
        ->assertJsonPath('errors.0.code', 'validation_error');

    $this->patchJson('/api/v1/learning/modules/reorder', [
        'course_id' => $course->id,
        'module_ids' => [],
    ], $headers)
        ->assertStatus(422)
        ->assertJsonPath('errors.0.code', 'validation_error');

    $this->patchJson('/api/v1/learning/modules/reorder', [
        'course_id' => $course->id,
        'module_ids' => [$moduleA->id, $moduleA->id],
    ], $headers)
        ->assertStatus(422)
        ->assertJsonPath('errors.0.code', 'validation_error');

    $this->patchJson('/api/v1/learning/modules/reorder', [
        'course_id' => $course->id,
        'module_ids' => [$moduleA->id],
    ], $headers)
        ->assertStatus(422)
        ->assertJsonPath('errors.0.code', 'validation_error');

    $this->patchJson('/api/v1/learning/modules/reorder', [
        'course_id' => $course->id,
        'module_ids' => [$moduleA->id, $moduleB->id, $foreignModule->id],
    ], $headers)
        ->assertStatus(422)
        ->assertJsonPath('errors.0.code', 'validation_error');
});

it('returns 401 without authentication', function (): void {
    $tenant = makeTenant(['domain' => 'tenant-a.local']);
    $course = Course::query()->create([
        'tenant_id' => $tenant->id,
        'title' => 'Course A',
        'slug' => 'course-a',
        'description' => 'Course description',
        'status' => 'draft',
        'price_cents' => 0,
        'access_days' => 30,
        'is_featured' => false,
    ]);

    $this->postJson('/api/v1/learning/modules', [
        'course_id' => $course->id,
        'title' => 'New module',
    ], tenantHeaders($tenant))
        ->assertUnauthorized();
});

it('returns 403 for a student without permission', function (): void {
    $tenant = makeTenant(['domain' => 'tenant-a.local']);
    [, $headers] = actingAsUserType(UserType::Student, $tenant);

    $course = Course::query()->create([
        'tenant_id' => $tenant->id,
        'title' => 'Course A',
        'slug' => 'course-a',
        'description' => 'Course description',
        'status' => 'draft',
        'price_cents' => 0,
        'access_days' => 30,
        'is_featured' => false,
    ]);

    $this->postJson('/api/v1/learning/modules', [
        'course_id' => $course->id,
        'title' => 'New module',
    ], $headers)
        ->assertForbidden();
});

it('validates course_id and title and blocks cross-tenant course ids', function (): void {
    $tenantA = makeTenant(['domain' => 'tenant-a.local']);
    $tenantB = makeTenant(['domain' => 'tenant-b.local']);
    [, $headers] = actingAsUserType(UserType::Admin, $tenantA);

    $foreignCourse = Course::query()->create([
        'tenant_id' => $tenantB->id,
        'title' => 'Foreign course',
        'slug' => 'foreign-course',
        'description' => 'Course description',
        'status' => 'draft',
        'price_cents' => 0,
        'access_days' => 30,
        'is_featured' => false,
    ]);

    $this->postJson('/api/v1/learning/modules', [
        'title' => 'Missing course id',
    ], $headers)
        ->assertStatus(422)
        ->assertJsonPath('errors.0.code', 'validation_error');

    $this->postJson('/api/v1/learning/modules', [
        'course_id' => $foreignCourse->id,
        'title' => 'Foreign module',
    ], $headers)
        ->assertStatus(422)
        ->assertJsonPath('errors.0.code', 'validation_error');

    $this->postJson('/api/v1/learning/modules', [
        'course_id' => $foreignCourse->id,
    ], $headers)
        ->assertStatus(422)
        ->assertJsonPath('errors.0.code', 'validation_error');
});

it('rejects a soft-deleted course id', function (): void {
    $tenant = makeTenant(['domain' => 'tenant-a.local']);
    [, $headers] = actingAsUserType(UserType::Admin, $tenant);

    $course = Course::query()->create([
        'tenant_id' => $tenant->id,
        'title' => 'Archived course',
        'slug' => 'archived-course',
        'description' => 'Course description',
        'status' => 'draft',
        'price_cents' => 0,
        'access_days' => 30,
        'is_featured' => false,
    ]);

    $course->delete();

    $this->postJson('/api/v1/learning/modules', [
        'course_id' => $course->id,
        'title' => 'Should fail',
    ], $headers)
        ->assertStatus(422)
        ->assertJsonPath('errors.0.code', 'validation_error');
});
