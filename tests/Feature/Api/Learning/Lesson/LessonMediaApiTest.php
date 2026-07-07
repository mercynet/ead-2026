<?php

use App\Modules\Core\Enums\UserType;
use App\Modules\Core\Models\Tenant;
use App\Modules\Learning\Models\Course;
use App\Modules\Learning\Models\CourseModule;
use App\Modules\Learning\Models\Lesson;
use App\Modules\Learning\Models\LessonMedia;

function makeLessonForTenant(Tenant $tenant): Lesson
{
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

    return Lesson::query()->create([
        'tenant_id' => $tenant->id,
        'course_module_id' => $module->id,
        'title' => 'Lesson A',
        'slug' => 'lesson-a',
        'status' => 'draft',
        'sort_order' => 1,
        'is_free' => false,
        'is_active' => true,
    ]);
}

it('creates lesson media for an authorized user and appends sort order by default', function (): void {
    $tenant = makeTenant(['domain' => 'tenant-a.local']);
    [, $headers] = actingAsUserType(UserType::Instructor, $tenant);
    $lesson = makeLessonForTenant($tenant);

    LessonMedia::query()->create([
        'tenant_id' => $tenant->id,
        'lesson_id' => $lesson->id,
        'media_type' => 'video',
        'provider' => 'embed',
        'provider_ref' => 'existing-media',
        'url' => 'https://video.example/existing',
        'sort_order' => 1,
        'is_active' => true,
    ]);

    $this->postJson("/api/v1/learning/lessons/{$lesson->id}/media", [
        'media_type' => 'video',
        'provider' => 'embed',
        'provider_ref' => 'new-media',
        'url' => 'https://video.example/new',
        'duration_seconds' => 320,
        'metadata' => ['quality' => 'hd'],
    ], $headers)
        ->assertCreated()
        ->assertJsonPath('data.lesson_id', $lesson->id)
        ->assertJsonPath('data.media_type', 'video')
        ->assertJsonPath('data.provider_ref', 'new-media')
        ->assertJsonPath('data.sort_order', 2)
        ->assertJsonPath('data.is_active', true)
        ->assertJsonPath('data.metadata.quality', 'hd');

    $this->assertDatabaseHas('lesson_media', [
        'tenant_id' => $tenant->id,
        'lesson_id' => $lesson->id,
        'provider_ref' => 'new-media',
        'sort_order' => 2,
        'is_active' => 1,
    ]);
});

it('returns 403 for a student without permission when creating lesson media', function (): void {
    $tenant = makeTenant(['domain' => 'tenant-a.local']);
    [, $headers] = actingAsUserType(UserType::Student, $tenant);
    $lesson = makeLessonForTenant($tenant);

    $this->postJson("/api/v1/learning/lessons/{$lesson->id}/media", [
        'media_type' => 'video',
        'provider' => 'embed',
    ], $headers)->assertForbidden();
});

it('returns 401 without authentication when creating lesson media', function (): void {
    $tenant = makeTenant(['domain' => 'tenant-a.local']);
    $lesson = makeLessonForTenant($tenant);

    $this->postJson("/api/v1/learning/lessons/{$lesson->id}/media", [
        'media_type' => 'video',
    ], tenantHeaders($tenant))->assertUnauthorized();
});

it('returns 404 for a missing or foreign lesson when creating lesson media', function (): void {
    $tenantA = makeTenant(['domain' => 'tenant-a.local']);
    $tenantB = makeTenant(['domain' => 'tenant-b.local']);
    [, $headers] = actingAsUserType(UserType::Admin, $tenantA);
    $foreignLesson = makeLessonForTenant($tenantB);

    $this->postJson('/api/v1/learning/lessons/999999/media', [
        'media_type' => 'video',
    ], $headers)->assertNotFound();

    $this->postJson("/api/v1/learning/lessons/{$foreignLesson->id}/media", [
        'media_type' => 'video',
    ], $headers)->assertNotFound();
});

it('returns 422 for invalid lesson media create payload', function (): void {
    $tenant = makeTenant(['domain' => 'tenant-a.local']);
    [, $headers] = actingAsUserType(UserType::Admin, $tenant);
    $lesson = makeLessonForTenant($tenant);

    assertApiErrorEnvelope(
        $this->postJson("/api/v1/learning/lessons/{$lesson->id}/media", [
            'media_type' => 'invalid-type',
            'url' => 'not-a-url',
            'duration_seconds' => 0,
        ], $headers),
        422,
        'validation_error'
    );
});

it('updates lesson media for an authorized user in the current tenant', function (): void {
    $tenant = makeTenant(['domain' => 'tenant-a.local']);
    [, $headers] = actingAsUserType(UserType::Instructor, $tenant);
    $lesson = makeLessonForTenant($tenant);
    $lessonMedia = LessonMedia::query()->create([
        'tenant_id' => $tenant->id,
        'lesson_id' => $lesson->id,
        'media_type' => 'video',
        'provider' => 'embed',
        'provider_ref' => 'old-ref',
        'url' => 'https://video.example/old',
        'sort_order' => 1,
        'is_active' => true,
    ]);

    $this->patchJson("/api/v1/learning/lessons/{$lesson->id}/media/{$lessonMedia->id}", [
        'provider_ref' => 'updated-ref',
        'url' => 'https://video.example/new',
        'is_active' => false,
        'metadata' => ['quality' => 'full-hd'],
    ], $headers)
        ->assertOk()
        ->assertJsonPath('data.id', $lessonMedia->id)
        ->assertJsonPath('data.lesson_id', $lesson->id)
        ->assertJsonPath('data.provider_ref', 'updated-ref')
        ->assertJsonPath('data.url', 'https://video.example/new')
        ->assertJsonPath('data.is_active', false)
        ->assertJsonPath('data.metadata.quality', 'full-hd');

    expect($lessonMedia->refresh()->provider_ref)->toBe('updated-ref');
    expect($lessonMedia->refresh()->is_active)->toBeFalse();
});

it('returns 403 for a student without permission when updating lesson media', function (): void {
    $tenant = makeTenant(['domain' => 'tenant-a.local']);
    [, $headers] = actingAsUserType(UserType::Student, $tenant);
    $lesson = makeLessonForTenant($tenant);
    $lessonMedia = LessonMedia::factory()->create([
        'tenant_id' => $tenant->id,
        'lesson_id' => $lesson->id,
    ]);

    $this->patchJson("/api/v1/learning/lessons/{$lesson->id}/media/{$lessonMedia->id}", [
        'provider_ref' => 'updated-ref',
    ], $headers)->assertForbidden();
});

it('returns 404 for a missing or foreign lesson media when updating', function (): void {
    $tenantA = makeTenant(['domain' => 'tenant-a.local']);
    $tenantB = makeTenant(['domain' => 'tenant-b.local']);
    [, $headers] = actingAsUserType(UserType::Admin, $tenantA);
    $lessonA = makeLessonForTenant($tenantA);
    $lessonB = makeLessonForTenant($tenantB);
    $foreignMedia = LessonMedia::factory()->create([
        'tenant_id' => $tenantB->id,
        'lesson_id' => $lessonB->id,
    ]);

    $this->patchJson("/api/v1/learning/lessons/{$lessonA->id}/media/999999", [
        'provider_ref' => 'updated-ref',
    ], $headers)->assertNotFound();

    $this->patchJson("/api/v1/learning/lessons/{$lessonA->id}/media/{$foreignMedia->id}", [
        'provider_ref' => 'updated-ref',
    ], $headers)->assertNotFound();
});

it('returns 422 for invalid lesson media update payload', function (): void {
    $tenant = makeTenant(['domain' => 'tenant-a.local']);
    [, $headers] = actingAsUserType(UserType::Admin, $tenant);
    $lesson = makeLessonForTenant($tenant);
    $lessonMedia = LessonMedia::factory()->create([
        'tenant_id' => $tenant->id,
        'lesson_id' => $lesson->id,
    ]);

    assertApiErrorEnvelope(
        $this->patchJson("/api/v1/learning/lessons/{$lesson->id}/media/{$lessonMedia->id}", [
            'media_type' => 'invalid-type',
            'duration_seconds' => 0,
        ], $headers),
        422,
        'validation_error'
    );
});

it('deletes lesson media for an authorized user in the current tenant', function (): void {
    $tenant = makeTenant(['domain' => 'tenant-a.local']);
    [, $headers] = actingAsUserType(UserType::Admin, $tenant);
    $lesson = makeLessonForTenant($tenant);
    $lessonMedia = LessonMedia::factory()->create([
        'tenant_id' => $tenant->id,
        'lesson_id' => $lesson->id,
    ]);

    $this->deleteJson("/api/v1/learning/lessons/{$lesson->id}/media/{$lessonMedia->id}", [], $headers)
        ->assertOk()
        ->assertJson(['message' => 'Lesson media deleted successfully.']);

    $this->assertDatabaseMissing('lesson_media', [
        'id' => $lessonMedia->id,
    ]);
});

it('returns 403 for a student without permission when deleting lesson media', function (): void {
    $tenant = makeTenant(['domain' => 'tenant-a.local']);
    [, $headers] = actingAsUserType(UserType::Student, $tenant);
    $lesson = makeLessonForTenant($tenant);
    $lessonMedia = LessonMedia::factory()->create([
        'tenant_id' => $tenant->id,
        'lesson_id' => $lesson->id,
    ]);

    $this->deleteJson("/api/v1/learning/lessons/{$lesson->id}/media/{$lessonMedia->id}", [], $headers)
        ->assertForbidden();
});

it('returns 404 for a missing or foreign lesson media when deleting', function (): void {
    $tenantA = makeTenant(['domain' => 'tenant-a.local']);
    $tenantB = makeTenant(['domain' => 'tenant-b.local']);
    [, $headers] = actingAsUserType(UserType::Admin, $tenantA);
    $lessonA = makeLessonForTenant($tenantA);
    $lessonB = makeLessonForTenant($tenantB);
    $foreignMedia = LessonMedia::factory()->create([
        'tenant_id' => $tenantB->id,
        'lesson_id' => $lessonB->id,
    ]);

    $this->deleteJson("/api/v1/learning/lessons/{$lessonA->id}/media/999999", [], $headers)
        ->assertNotFound();

    $this->deleteJson("/api/v1/learning/lessons/{$lessonA->id}/media/{$foreignMedia->id}", [], $headers)
        ->assertNotFound();
});
