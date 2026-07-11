<?php

use App\Modules\Core\Enums\UserType;
use App\Modules\Core\Models\Tenant;
use App\Modules\Core\Models\User;
use App\Modules\Learning\Models\Course;
use App\Modules\Learning\Models\CourseModule;
use App\Modules\Learning\Models\Enrollment;
use App\Modules\Learning\Models\Lesson;
use App\Modules\Learning\Models\LessonMedia;

function makeLessonForTenant(Tenant $tenant, ?User $instructor = null): Lesson
{
    $course = Course::query()->create([
        'tenant_id' => $tenant->id,
        'instructor_id' => $instructor?->id,
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
    [$instructor, $headers] = actingAsUserType(UserType::Instructor, $tenant);
    $lesson = makeLessonForTenant($tenant, $instructor);

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
        ->assertJsonPath('data.progress_strategy', '80_percent')
        ->assertJsonPath('data.sort_order', 2)
        ->assertJsonPath('data.is_active', true)
        ->assertJsonPath('data.metadata.quality', 'hd');

    $this->assertDatabaseHas('lesson_media', [
        'tenant_id' => $tenant->id,
        'lesson_id' => $lesson->id,
        'provider_ref' => 'new-media',
        'progress_strategy' => '80_percent',
        'sort_order' => 2,
        'is_active' => 1,
    ]);
});

it('creates typed lesson media payload for storage providers and time based progress', function (): void {
    $tenant = makeTenant(['domain' => 'tenant-a.local']);
    [$instructor, $headers] = actingAsUserType(UserType::Instructor, $tenant);
    $lesson = makeLessonForTenant($tenant, $instructor);

    $this->postJson("/api/v1/learning/lessons/{$lesson->id}/media", [
        'media_type' => 'video',
        'provider' => 's3',
        'provider_ref' => 'lesson-storage-video',
        'progress_strategy' => 'time_based',
        'duration_seconds' => 600,
        'metadata' => [
            'storage_path' => 'tenants/'.$tenant->id.'/lessons/storage-video.mp4',
            'storage_disk' => 's3',
            'required_seconds' => 180,
        ],
    ], $headers)
        ->assertCreated()
        ->assertJsonPath('data.provider', 's3')
        ->assertJsonPath('data.progress_strategy', 'time_based')
        ->assertJsonPath('data.provider_config.storage_path', 'tenants/'.$tenant->id.'/lessons/storage-video.mp4')
        ->assertJsonPath('data.provider_config.storage_disk', 's3')
        ->assertJsonPath('data.progress_config.required_seconds', 180)
        ->assertJsonPath('data.metadata.required_seconds', 180);

    $this->assertDatabaseHas('lesson_media', [
        'tenant_id' => $tenant->id,
        'lesson_id' => $lesson->id,
        'provider' => 's3',
        'progress_strategy' => 'time_based',
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

it('returns 422 when typed lesson media payload misses subtype requirements', function (): void {
    $tenant = makeTenant(['domain' => 'tenant-a.local']);
    [, $headers] = actingAsUserType(UserType::Admin, $tenant);
    $lesson = makeLessonForTenant($tenant);

    assertApiErrorEnvelope(
        $this->postJson("/api/v1/learning/lessons/{$lesson->id}/media", [
            'media_type' => 'video',
            'provider' => 'youtube',
            'progress_strategy' => 'time_based',
            'metadata' => [],
        ], $headers),
        422,
        'validation_error'
    );
});

it('rejects unsafe storage metadata on lesson media create and update', function (): void {
    $tenant = makeTenant(['domain' => 'tenant-a.local']);
    [, $headers] = actingAsUserType(UserType::Admin, $tenant);
    $lesson = makeLessonForTenant($tenant);

    assertApiErrorEnvelope(
        $this->postJson("/api/v1/learning/lessons/{$lesson->id}/media", [
            'media_type' => 'video',
            'provider' => 's3',
            'metadata' => [
                'storage_path' => 'shared/lesson.mp4',
                'storage_disk' => 'public',
            ],
        ], $headers),
        422,
        'validation_error'
    );

    $lessonMedia = LessonMedia::query()->create([
        'tenant_id' => $tenant->id,
        'lesson_id' => $lesson->id,
        'media_type' => 'video',
        'provider' => 's3',
        'provider_ref' => 'safe-media',
        'duration_seconds' => 300,
        'progress_strategy' => '80_percent',
        'sort_order' => 1,
        'is_active' => true,
        'metadata' => [
            'storage_disk' => 's3',
            'storage_path' => 'tenants/'.$tenant->id.'/lessons/safe-media.mp4',
        ],
    ]);

    assertApiErrorEnvelope(
        $this->patchJson("/api/v1/learning/lessons/{$lesson->id}/media/{$lessonMedia->id}", [
            'metadata' => [
                'storage_path' => 'tenants/'.$tenant->id.'/../escape.mp4',
                'storage_disk' => 'public',
            ],
        ], $headers),
        422,
        'validation_error'
    );
});

it('fails safe when a storage lesson media has invalid persisted metadata', function (): void {
    $tenant = makeTenant(['domain' => 'tenant-a.local']);
    [$student, $headers] = actingAsUserType(UserType::Student, $tenant);

    $course = Course::query()->create([
        'tenant_id' => $tenant->id,
        'title' => 'Unsafe Media Course',
        'slug' => 'unsafe-media-course',
        'description' => 'Course description',
        'status' => 'published',
        'price_cents' => 10000,
        'access_days' => 30,
        'is_featured' => false,
    ]);

    $module = CourseModule::query()->create([
        'tenant_id' => $tenant->id,
        'course_id' => $course->id,
        'title' => 'Module 1',
        'sort_order' => 1,
    ]);

    $lesson = Lesson::query()->create([
        'tenant_id' => $tenant->id,
        'course_module_id' => $module->id,
        'title' => 'Unsafe Media Lesson',
        'slug' => 'unsafe-media-lesson',
        'status' => 'published',
        'sort_order' => 1,
        'is_free' => false,
    ]);

    Enrollment::query()->create([
        'tenant_id' => $tenant->id,
        'user_id' => $student->id,
        'course_id' => $course->id,
        'status' => 'active',
        'enrolled_at' => now(),
        'access_expires_at' => now()->addDays(30),
        'progress_percentage' => 0,
    ]);

    LessonMedia::query()->create([
        'tenant_id' => $tenant->id,
        'lesson_id' => $lesson->id,
        'media_type' => 'video',
        'provider' => 's3',
        'provider_ref' => 'unsafe-media',
        'duration_seconds' => 300,
        'progress_strategy' => '80_percent',
        'sort_order' => 1,
        'is_active' => true,
        'metadata' => [
            'storage_disk' => 'public',
            'storage_path' => 'escape.mp4',
        ],
    ]);

    $this->getJson("/api/v1/learning/lessons/{$lesson->id}", $headers)
        ->assertSuccessful()
        ->assertJsonPath('data.media.0.url', null)
        ->assertJsonPath('data.media.0.url_kind', null);
});

it('updates lesson media for an authorized user in the current tenant', function (): void {
    $tenant = makeTenant(['domain' => 'tenant-a.local']);
    [$instructor, $headers] = actingAsUserType(UserType::Instructor, $tenant);
    $lesson = makeLessonForTenant($tenant, $instructor);
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
        'progress_strategy' => 'manual',
        'is_active' => false,
        'metadata' => ['quality' => 'full-hd'],
    ], $headers)
        ->assertOk()
        ->assertJsonPath('data.id', $lessonMedia->id)
        ->assertJsonPath('data.lesson_id', $lesson->id)
        ->assertJsonPath('data.provider_ref', 'updated-ref')
        ->assertJsonPath('data.url', 'https://video.example/new')
        ->assertJsonPath('data.progress_strategy', 'manual')
        ->assertJsonPath('data.is_active', false)
        ->assertJsonPath('data.metadata.quality', 'full-hd');

    expect($lessonMedia->refresh()->provider_ref)->toBe('updated-ref');
    expect($lessonMedia->refresh()->progress_strategy?->value)->toBe('manual');
    expect($lessonMedia->refresh()->is_active)->toBeFalse();
});

it('updates typed lesson media payload for provider specific configuration', function (): void {
    $tenant = makeTenant(['domain' => 'tenant-a.local']);
    [$instructor, $headers] = actingAsUserType(UserType::Instructor, $tenant);
    $lesson = makeLessonForTenant($tenant, $instructor);
    $lessonMedia = LessonMedia::query()->create([
        'tenant_id' => $tenant->id,
        'lesson_id' => $lesson->id,
        'media_type' => 'video',
        'provider' => 'youtube',
        'provider_ref' => 'old-video-id',
        'progress_strategy' => '80_percent',
        'sort_order' => 1,
        'is_active' => true,
        'metadata' => [],
    ]);

    $this->patchJson("/api/v1/learning/lessons/{$lesson->id}/media/{$lessonMedia->id}", [
        'provider' => 'vimeo',
        'provider_ref' => 'new-video-id',
        'progress_strategy' => 'time_based',
        'metadata' => [
            'player_url' => 'https://player.vimeo.com/video/new-video-id',
            'required_seconds' => 240,
        ],
    ], $headers)
        ->assertOk()
        ->assertJsonPath('data.provider', 'vimeo')
        ->assertJsonPath('data.provider_ref', 'new-video-id')
        ->assertJsonPath('data.progress_strategy', 'time_based')
        ->assertJsonPath('data.provider_config.video_id', 'new-video-id')
        ->assertJsonPath('data.provider_config.player_url', 'https://player.vimeo.com/video/new-video-id')
        ->assertJsonPath('data.progress_config.required_seconds', 240);

    expect($lessonMedia->refresh()->provider)->toBe('vimeo');
    expect($lessonMedia->refresh()->progress_strategy?->value)->toBe('time_based');
    expect(data_get($lessonMedia->refresh()->metadata, 'required_seconds'))->toBe(240);
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
