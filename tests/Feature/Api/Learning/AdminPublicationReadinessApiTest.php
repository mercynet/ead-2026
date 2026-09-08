<?php

use App\Modules\Assessment\Models\Questionnaire;
use App\Modules\Core\Enums\UserType;
use App\Modules\Learning\Models\Course;
use App\Modules\Learning\Models\CourseModule;
use App\Modules\Learning\Models\Lesson;
use Spatie\Permission\Models\Role;

it('requires an explicit published active lesson before publishing a course', function (): void {
    $tenant = makeTenant();
    [, $headers] = actingAsUserType(UserType::Admin, $tenant);
    $course = Course::factory()->draft()->create([
        'tenant_id' => $tenant->id,
        'is_active' => true,
        'instructor_id' => null,
    ]);

    assertApiErrorEnvelope(
        $this->postJson('/api/v1/admin/courses/'.$course->id.'/publish', [], $headers),
        422,
        'validation_error'
    );

    expect($course->refresh()->status)->toBe('draft')
        ->and($course->published_at)->toBeNull();

    $module = CourseModule::factory()->for($tenant)->for($course)->create();

    assertApiErrorEnvelope(
        $this->postJson('/api/v1/admin/courses/'.$course->id.'/publish', [], $headers),
        422,
        'validation_error'
    );

    $lesson = Lesson::factory()->for($tenant)->for($module)->create([
        'status' => 'draft',
        'is_active' => true,
        'published_at' => null,
    ]);

    assertApiErrorEnvelope(
        $this->postJson('/api/v1/admin/courses/'.$course->id.'/publish', [], $headers),
        422,
        'validation_error'
    );

    $this->postJson('/api/v1/admin/lessons/'.$lesson->id.'/publish', [], $headers)
        ->assertSuccessful()
        ->assertJsonPath('data.status', 'published');

    $lesson->refresh();
    expect($lesson->status)->toBe('published')
        ->and($lesson->is_active)->toBeTrue()
        ->and($lesson->published_at)->not->toBeNull();

    $this->postJson('/api/v1/admin/courses/'.$course->id.'/publish', [], $headers)
        ->assertSuccessful()
        ->assertJsonPath('data.status', 'published');

    expect($course->refresh()->instructor_id)->toBeNull()
        ->and($course->status)->toBe('published')
        ->and($course->published_at)->not->toBeNull();
});

it('publishes and unpublishes a lesson only through the explicit admin transition', function (): void {
    $tenant = makeTenant();
    [, $headers] = actingAsUserType(UserType::Admin, $tenant);
    $course = Course::factory()->draft()->create(['tenant_id' => $tenant->id]);
    $module = CourseModule::factory()->for($tenant)->for($course)->create();
    $lesson = Lesson::factory()->for($tenant)->for($module)->create([
        'status' => 'draft',
        'is_active' => true,
        'published_at' => null,
    ]);

    $this->postJson('/api/v1/admin/lessons/'.$lesson->id.'/publish', [], $headers)
        ->assertSuccessful()
        ->assertJsonPath('data.status', 'published');

    $publishedAt = $lesson->refresh()->published_at;

    assertApiErrorEnvelope(
        $this->patchJson('/api/v1/admin/lessons/'.$lesson->id, [
            'title' => 'Tentativa de alterar status via CRUD',
            'status' => 'published',
        ], $headers),
        422,
        'validation_error'
    );

    expect($lesson->refresh()->status)->toBe('published')
        ->and($lesson->published_at)->toEqual($publishedAt);

    $this->postJson('/api/v1/admin/lessons/'.$lesson->id.'/unpublish', [], $headers)
        ->assertSuccessful()
        ->assertJsonPath('data.status', 'draft');

    expect($lesson->refresh()->status)->toBe('draft')
        ->and($lesson->published_at)->toEqual($publishedAt);
});

it('requires authentication and lesson update permission to publish', function (): void {
    $tenant = makeTenant();
    [$admin, $headers] = actingAsUserType(UserType::Admin, $tenant);
    $course = Course::factory()->draft()->create(['tenant_id' => $tenant->id]);
    $module = CourseModule::factory()->for($tenant)->for($course)->create();
    $lesson = Lesson::factory()->for($tenant)->for($module)->create(['status' => 'draft']);

    assertApiErrorEnvelope(
        $this->postJson('/api/v1/admin/lessons/'.$lesson->id.'/publish', [], tenantHeaders($tenant)),
        401,
        'unauthenticated'
    );

    $role = Role::query()->create([
        'name' => 'admin-without-lesson-update',
        'guard_name' => 'web',
    ]);
    $role->syncPermissions(collect(config('permissions'))
        ->keys()
        ->reject(fn (string $permission): bool => $permission === 'learning.lessons.update')
        ->all());
    $admin->syncRoles($role);

    assertApiErrorEnvelope(
        $this->postJson('/api/v1/admin/lessons/'.$lesson->id.'/publish', [], $headers),
        403,
        'access_denied'
    );
});

it('rejects inactive courses without changing publication state', function (): void {
    $tenant = makeTenant();
    [, $headers] = actingAsUserType(UserType::Admin, $tenant);
    $course = Course::factory()->draft()->create([
        'tenant_id' => $tenant->id,
        'is_active' => false,
    ]);
    $module = CourseModule::factory()->for($tenant)->for($course)->create();
    Lesson::factory()->for($tenant)->for($module)->create([
        'status' => 'published',
        'is_active' => true,
    ]);

    assertApiErrorEnvelope(
        $this->postJson('/api/v1/admin/courses/'.$course->id.'/publish', [], $headers),
        422,
        'validation_error'
    );

    expect($course->refresh()->status)->toBe('draft')
        ->and($course->published_at)->toBeNull();
});

it('keeps lesson publication area-guarded for instructors', function (): void {
    $tenant = makeTenant();
    [, $instructorHeaders] = actingAsUserType(UserType::Instructor, $tenant);
    $course = Course::factory()->draft()->create(['tenant_id' => $tenant->id]);
    $module = CourseModule::factory()->for($tenant)->for($course)->create();
    $lesson = Lesson::factory()->for($tenant)->for($module)->create(['status' => 'draft']);

    assertApiErrorEnvelope(
        $this->postJson('/api/v1/admin/lessons/'.$lesson->id.'/publish', [], $instructorHeaders),
        403,
        'area_forbidden'
    );
});

it('keeps lesson publication tenant-scoped', function (): void {
    $tenantA = makeTenant();
    $tenantB = makeTenant();
    [, $headersA] = actingAsUserType(UserType::Admin, $tenantA);
    $course = Course::factory()->draft()->create(['tenant_id' => $tenantB->id]);
    $module = CourseModule::factory()->for($tenantB)->for($course)->create();
    $lesson = Lesson::factory()->for($tenantB)->for($module)->create(['status' => 'draft']);

    assertApiErrorEnvelope(
        $this->postJson('/api/v1/admin/lessons/'.$lesson->id.'/publish', [], $headersA),
        404,
        'not_found'
    );
});

it('rejects publishing a course whose commercial completion requires assessment', function (): void {
    $tenant = makeTenant();
    [, $headers] = actingAsUserType(UserType::Admin, $tenant);
    $course = Course::factory()->draft()->create([
        'tenant_id' => $tenant->id,
        'is_active' => true,
        'certificate_requires_quiz' => true,
    ]);
    $module = CourseModule::factory()->for($tenant)->for($course)->create();
    Lesson::factory()->for($tenant)->for($module)->create([
        'status' => 'published',
        'is_active' => true,
    ]);

    $response = assertApiErrorEnvelope(
        $this->postJson('/api/v1/admin/courses/'.$course->id.'/publish', [], $headers),
        422,
        'validation_error'
    );

    $response->assertJsonPath('errors.0.message', 'Course is not commercially ready: Student Assessment is not available for a required completion condition.');
    expect($course->refresh()->status)->toBe('draft')
        ->and($course->published_at)->toBeNull();
});

it('rejects publishing a course that promises a certificate', function (): void {
    $tenant = makeTenant();
    [, $headers] = actingAsUserType(UserType::Admin, $tenant);
    $course = Course::factory()->draft()->create([
        'tenant_id' => $tenant->id,
        'is_active' => true,
        'certificate_enabled' => true,
    ]);
    $module = CourseModule::factory()->for($tenant)->for($course)->create();
    Lesson::factory()->for($tenant)->for($module)->create([
        'status' => 'published',
        'is_active' => true,
    ]);

    $response = assertApiErrorEnvelope(
        $this->postJson('/api/v1/admin/courses/'.$course->id.'/publish', [], $headers),
        422,
        'validation_error'
    );

    $response->assertJsonPath('errors.0.message', 'Course is not commercially ready: certificates are not available in the current release.');
    expect($course->refresh()->status)->toBe('draft')
        ->and($course->published_at)->toBeNull();
});

it('keeps a course without unreleased capability requirements publishable', function (): void {
    $tenant = makeTenant();
    [, $headers] = actingAsUserType(UserType::Admin, $tenant);
    $course = Course::factory()->draft()->create([
        'tenant_id' => $tenant->id,
        'is_active' => true,
        'certificate_enabled' => false,
        'certificate_requires_quiz' => false,
    ]);
    $module = CourseModule::factory()->for($tenant)->for($course)->create();
    Lesson::factory()->for($tenant)->for($module)->create([
        'status' => 'published',
        'is_active' => true,
    ]);

    $this->postJson('/api/v1/admin/courses/'.$course->id.'/publish', [], $headers)
        ->assertSuccessful()
        ->assertJsonPath('data.status', 'published');

    expect($course->refresh()->published_at)->not->toBeNull();
});

it('does not treat an incidental course questionnaire as a commercial requirement', function (): void {
    $tenant = makeTenant();
    [, $headers] = actingAsUserType(UserType::Admin, $tenant);
    $course = Course::factory()->draft()->create([
        'tenant_id' => $tenant->id,
        'is_active' => true,
        'certificate_enabled' => false,
        'certificate_requires_quiz' => false,
    ]);
    $module = CourseModule::factory()->for($tenant)->for($course)->create();
    Lesson::factory()->for($tenant)->for($module)->create([
        'status' => 'published',
        'is_active' => true,
    ]);
    Questionnaire::factory()->course()->create([
        'tenant_id' => $tenant->id,
        'instructor_id' => null,
        'quizable_id' => $course->id,
        'quizable_type' => 'course',
    ]);

    $this->postJson('/api/v1/admin/courses/'.$course->id.'/publish', [], $headers)
        ->assertSuccessful()
        ->assertJsonPath('data.status', 'published');
});

it('cannot publish a course through generic course update', function (): void {
    $tenant = makeTenant();
    [, $headers] = actingAsUserType(UserType::Admin, $tenant);
    $course = Course::factory()->draft()->create([
        'tenant_id' => $tenant->id,
        'is_active' => true,
    ]);

    assertApiErrorEnvelope(
        $this->patchJson('/api/v1/admin/courses/'.$course->id, [
            'status' => 'published',
        ], $headers),
        422,
        'validation_error'
    );

    expect($course->refresh()->status)->toBe('draft')
        ->and($course->published_at)->toBeNull();

    $this->patchJson('/api/v1/admin/courses/'.$course->id, [
        'certificate_enabled' => true,
    ], $headers)
        ->assertSuccessful();

    expect($course->refresh()->status)->toBe('draft')
        ->and($course->certificate_enabled)->toBeTrue();

    assertApiErrorEnvelope(
        $this->postJson('/api/v1/admin/courses/'.$course->id.'/publish', [], $headers),
        422,
        'validation_error'
    );

    expect($course->refresh()->status)->toBe('draft')
        ->and($course->published_at)->toBeNull();
});

it('rejects adding an unreleased requirement to an already published course', function (): void {
    $tenant = makeTenant();
    [, $headers] = actingAsUserType(UserType::Admin, $tenant);
    $course = Course::factory()->draft()->create([
        'tenant_id' => $tenant->id,
        'is_active' => true,
    ]);
    $module = CourseModule::factory()->for($tenant)->for($course)->create();
    Lesson::factory()->for($tenant)->for($module)->create([
        'status' => 'published',
        'is_active' => true,
    ]);

    $this->postJson('/api/v1/admin/courses/'.$course->id.'/publish', [], $headers)
        ->assertSuccessful();

    $response = assertApiErrorEnvelope(
        $this->patchJson('/api/v1/admin/courses/'.$course->id, [
            'certificate_requires_quiz' => true,
        ], $headers),
        422,
        'validation_error'
    );

    $response->assertJsonPath('errors.0.message', 'Course is not commercially ready: Student Assessment is not available for a required completion condition.');
    expect($course->refresh()->status)->toBe('published')
        ->and($course->certificate_requires_quiz)->toBeFalse();
});
