<?php

use App\Modules\Core\Enums\UserType;
use App\Modules\Learning\Models\Course;
use App\Modules\Learning\Models\CourseModule;
use App\Modules\Learning\Models\Enrollment;
use App\Modules\Learning\Models\Lesson;
use App\Modules\Learning\Models\LessonMedia;
use App\Modules\Learning\Models\LessonMediaProgress;
use App\Modules\Learning\Models\LessonProgress;
use App\Modules\Learning\Models\LessonView;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

it('shows lesson with can_access true for free lesson', function (): void {
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

    $module = CourseModule::query()->create([
        'tenant_id' => $tenant->id,
        'course_id' => $course->id,
        'title' => 'Module 1',
        'sort_order' => 1,
    ]);

    $lesson = Lesson::query()->create([
        'tenant_id' => $tenant->id,
        'course_module_id' => $module->id,
        'title' => 'Free Lesson',
        'slug' => 'free-lesson',
        'status' => 'published',
        'sort_order' => 1,
        'is_free' => true,
    ]);

    $this->getJson("/api/v1/learning/lessons/{$lesson->id}", $headers)
        ->assertSuccessful()
        ->assertJsonPath('data.id', $lesson->id)
        ->assertJsonPath('data.title', 'Free Lesson')
        ->assertJsonPath('data.can_access', true)
        ->assertJsonPath('data.is_free', true);

    $this->assertDatabaseHas('lesson_views', [
        'tenant_id' => $tenant->id,
        'user_id' => $student->id,
        'lesson_id' => $lesson->id,
    ]);
});

it('creates a lesson in the current tenant', function (): void {
    $tenant = makeTenant(['domain' => 'tenant-a.local']);
    [, $headers] = actingAsUserType(UserType::Instructor, $tenant);

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

    Lesson::query()->create([
        'tenant_id' => $tenant->id,
        'course_module_id' => $module->id,
        'title' => 'Lesson 1',
        'slug' => 'lesson-1',
        'status' => 'draft',
        'sort_order' => 2,
        'is_free' => true,
        'is_active' => true,
    ]);

    $this->postJson('/api/v1/learning/lessons', [
        'course_module_id' => $module->id,
        'title' => 'New Lesson',
    ], $headers)
        ->assertCreated()
        ->assertJsonPath('data.title', 'New Lesson')
        ->assertJsonPath('data.sort_order', 3)
        ->assertJsonPath('data.is_free', false);

    $this->assertDatabaseHas('lessons', [
        'tenant_id' => $tenant->id,
        'course_module_id' => $module->id,
        'title' => 'New Lesson',
        'slug' => 'new-lesson',
        'status' => 'draft',
        'sort_order' => 3,
        'is_free' => false,
        'is_active' => true,
    ]);
});

it('reorders lessons for an authorized user in the current tenant', function (): void {
    $tenant = makeTenant(['domain' => 'tenant-a.local']);
    [, $headers] = actingAsUserType(UserType::Instructor, $tenant);

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

    $lesson1 = Lesson::query()->create([
        'tenant_id' => $tenant->id,
        'course_module_id' => $module->id,
        'title' => 'Lesson 1',
        'slug' => 'lesson-1',
        'status' => 'draft',
        'sort_order' => 1,
        'is_free' => false,
    ]);

    $lesson2 = Lesson::query()->create([
        'tenant_id' => $tenant->id,
        'course_module_id' => $module->id,
        'title' => 'Lesson 2',
        'slug' => 'lesson-2',
        'status' => 'draft',
        'sort_order' => 2,
        'is_free' => false,
    ]);

    $lesson3 = Lesson::query()->create([
        'tenant_id' => $tenant->id,
        'course_module_id' => $module->id,
        'title' => 'Lesson 3',
        'slug' => 'lesson-3',
        'status' => 'draft',
        'sort_order' => 3,
        'is_free' => false,
    ]);

    $this->patchJson('/api/v1/learning/lessons/reorder', [
        'course_module_id' => $module->id,
        'lesson_ids' => [$lesson3->id, $lesson1->id, $lesson2->id],
    ], $headers)
        ->assertOk()
        ->assertJsonPath('data.0.id', $lesson3->id)
        ->assertJsonPath('data.0.sort_order', 1)
        ->assertJsonPath('data.1.id', $lesson1->id)
        ->assertJsonPath('data.1.sort_order', 2)
        ->assertJsonPath('data.2.id', $lesson2->id)
        ->assertJsonPath('data.2.sort_order', 3);

    expect($lesson1->refresh()->sort_order)->toBe(2);
    expect($lesson2->refresh()->sort_order)->toBe(3);
    expect($lesson3->refresh()->sort_order)->toBe(1);
});

it('returns 403 for a student without permission when reordering lessons', function (): void {
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

    $lesson = Lesson::query()->create([
        'tenant_id' => $tenant->id,
        'course_module_id' => $module->id,
        'title' => 'Lesson 1',
        'slug' => 'lesson-1',
        'status' => 'draft',
        'sort_order' => 1,
        'is_free' => false,
    ]);

    $this->patchJson('/api/v1/learning/lessons/reorder', [
        'course_module_id' => $module->id,
        'lesson_ids' => [$lesson->id],
    ], $headers)
        ->assertForbidden();
});

it('returns 401 without authentication when reordering lessons', function (): void {
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

    $lesson = Lesson::query()->create([
        'tenant_id' => $tenant->id,
        'course_module_id' => $module->id,
        'title' => 'Lesson 1',
        'slug' => 'lesson-1',
        'status' => 'draft',
        'sort_order' => 1,
        'is_free' => false,
    ]);

    $this->patchJson('/api/v1/learning/lessons/reorder', [
        'course_module_id' => $module->id,
        'lesson_ids' => [$lesson->id],
    ], tenantHeaders($tenant))
        ->assertUnauthorized();
});

it('returns 422 for invalid lesson reorder payloads', function (): void {
    $tenant = makeTenant(['domain' => 'tenant-a.local']);
    [, $headers] = actingAsUserType(UserType::Instructor, $tenant);

    $this->patchJson('/api/v1/learning/lessons/reorder', [], $headers)
        ->assertUnprocessable()
        ->assertJsonPath('errors.0.code', 'validation_error');
});

it('returns 422 for missing or foreign lessons when reordering', function (): void {
    $tenantA = makeTenant(['domain' => 'tenant-a.local']);
    $tenantB = makeTenant(['domain' => 'tenant-b.local']);
    [, $headers] = actingAsUserType(UserType::Instructor, $tenantA);

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

    $module = CourseModule::query()->create([
        'tenant_id' => $tenantB->id,
        'course_id' => $course->id,
        'title' => 'Module B',
        'sort_order' => 1,
    ]);

    $lesson = Lesson::query()->create([
        'tenant_id' => $tenantB->id,
        'course_module_id' => $module->id,
        'title' => 'Foreign lesson',
        'slug' => 'foreign-lesson',
        'status' => 'draft',
        'sort_order' => 1,
        'is_free' => false,
    ]);

    $this->patchJson('/api/v1/learning/lessons/reorder', [
        'course_module_id' => $module->id,
        'lesson_ids' => [999999],
    ], $headers)
        ->assertUnprocessable()
        ->assertJsonPath('errors.0.code', 'validation_error');

    $this->patchJson('/api/v1/learning/lessons/reorder', [
        'course_module_id' => $module->id,
        'lesson_ids' => [$lesson->id],
    ], $headers)
        ->assertUnprocessable()
        ->assertJsonPath('errors.0.code', 'validation_error');
});

it('returns 403 for a student when creating a lesson', function (): void {
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

    $this->postJson('/api/v1/learning/lessons', [
        'course_module_id' => $module->id,
        'title' => 'New Lesson',
    ], $headers)
        ->assertForbidden();
});

it('returns 422 for invalid payload when creating a lesson', function (): void {
    $tenant = makeTenant(['domain' => 'tenant-a.local']);
    [, $headers] = actingAsUserType(UserType::Instructor, $tenant);

    $this->postJson('/api/v1/learning/lessons', [], $headers)
        ->assertUnprocessable()
        ->assertJsonPath('errors.0.code', 'validation_error');
});

it('returns 422 for missing or foreign module when creating a lesson', function (): void {
    $tenantA = makeTenant(['domain' => 'tenant-a.local']);
    $tenantB = makeTenant(['domain' => 'tenant-b.local']);
    [, $headers] = actingAsUserType(UserType::Instructor, $tenantA);

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

    $module = CourseModule::query()->create([
        'tenant_id' => $tenantB->id,
        'course_id' => $course->id,
        'title' => 'Module B',
        'sort_order' => 1,
    ]);

    $this->postJson('/api/v1/learning/lessons', [
        'course_module_id' => 999999,
        'title' => 'New Lesson',
    ], $headers)
        ->assertUnprocessable()
        ->assertJsonPath('errors.0.code', 'validation_error');

    $this->postJson('/api/v1/learning/lessons', [
        'course_module_id' => $module->id,
        'title' => 'New Lesson',
    ], $headers)
        ->assertUnprocessable()
        ->assertJsonPath('errors.0.code', 'validation_error');
});

it('returns 401 without authentication when creating a lesson', function (): void {
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

    $this->postJson('/api/v1/learning/lessons', [
        'course_module_id' => $module->id,
        'title' => 'New Lesson',
    ], tenantHeaders($tenant))
        ->assertUnauthorized();
});

it('denies access to paid lesson without enrollment', function (): void {
    $tenant = makeTenant(['domain' => 'tenant-a.local']);
    [$student, $headers] = actingAsUserType(UserType::Student, $tenant);

    $course = Course::query()->create([
        'tenant_id' => $tenant->id,
        'title' => 'Paid Course',
        'slug' => 'paid-course',
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
        'title' => 'Paid Lesson',
        'slug' => 'paid-lesson',
        'status' => 'published',
        'sort_order' => 1,
        'is_free' => false,
    ]);

    $this->getJson("/api/v1/learning/lessons/{$lesson->id}", $headers)
        ->assertSuccessful()
        ->assertJsonPath('data.can_access', false);
});

it('hides paid lesson media without enrollment', function (): void {
    $tenant = makeTenant(['domain' => 'tenant-a.local']);
    [, $headers] = actingAsUserType(UserType::Student, $tenant);

    $course = Course::query()->create([
        'tenant_id' => $tenant->id,
        'title' => 'Locked Media Course',
        'slug' => 'locked-media-course',
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
        'title' => 'Locked Media Lesson',
        'slug' => 'locked-media-lesson',
        'status' => 'published',
        'sort_order' => 1,
        'is_free' => false,
    ]);

    LessonMedia::query()->create([
        'tenant_id' => $tenant->id,
        'lesson_id' => $lesson->id,
        'media_type' => 'video',
        'provider' => 'embed',
        'provider_ref' => 'locked-video',
        'url' => 'https://video.example/locked',
        'sort_order' => 1,
        'is_active' => true,
    ]);

    $this->getJson("/api/v1/learning/lessons/{$lesson->id}", $headers)
        ->assertSuccessful()
        ->assertJsonPath('data.can_access', false)
        ->assertJsonPath('data.media', null);
});

it('allows access to paid lesson with active enrollment', function (): void {
    $tenant = makeTenant(['domain' => 'tenant-a.local']);
    [$student, $headers] = actingAsUserType(UserType::Student, $tenant);

    $course = Course::query()->create([
        'tenant_id' => $tenant->id,
        'title' => 'Paid Course',
        'slug' => 'paid-course',
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
        'title' => 'Paid Lesson',
        'slug' => 'paid-lesson',
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

    $this->getJson("/api/v1/learning/lessons/{$lesson->id}", $headers)
        ->assertSuccessful()
        ->assertJsonPath('data.can_access', true);
});

it('returns active lesson media for accessible paid lesson', function (): void {
    $tenant = makeTenant(['domain' => 'tenant-a.local']);
    [$student, $headers] = actingAsUserType(UserType::Student, $tenant);

    $course = Course::query()->create([
        'tenant_id' => $tenant->id,
        'title' => 'Media Course',
        'slug' => 'media-course',
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
        'title' => 'Media Lesson',
        'slug' => 'media-lesson',
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
        'provider' => 'embed',
        'provider_ref' => 'active-video',
        'url' => 'https://video.example/active',
        'content' => null,
        'duration_seconds' => 300,
        'sort_order' => 1,
        'is_active' => true,
        'metadata' => ['quality' => 'hd'],
    ]);

    LessonMedia::query()->create([
        'tenant_id' => $tenant->id,
        'lesson_id' => $lesson->id,
        'media_type' => 'video',
        'provider' => 'embed',
        'provider_ref' => 'inactive-video',
        'url' => 'https://video.example/inactive',
        'sort_order' => 2,
        'is_active' => false,
    ]);

    $this->getJson("/api/v1/learning/lessons/{$lesson->id}", $headers)
        ->assertSuccessful()
        ->assertJsonPath('data.can_access', true)
        ->assertJsonPath('data.media.0.media_type', 'video')
        ->assertJsonPath('data.media.0.provider', 'embed')
        ->assertJsonPath('data.media.0.provider_ref', 'active-video')
        ->assertJsonPath('data.media.0.url', 'https://video.example/active')
        ->assertJsonPath('data.media.0.url_kind', 'player')
        ->assertJsonPath('data.media.0.url_expires_at', null)
        ->assertJsonPath('data.media.0.progress_strategy', '80_percent')
        ->assertJsonPath('data.media.0.duration_seconds', 300)
        ->assertJsonCount(1, 'data.media');
});

it('resolves a temporary URL for internal lesson media when the lesson is accessible', function (): void {
    Date::setTestNow('2026-07-07 15:00:00');

    $tenant = makeTenant(['domain' => 'tenant-a.local']);
    [$student, $headers] = actingAsUserType(UserType::Student, $tenant);

    $course = Course::query()->create([
        'tenant_id' => $tenant->id,
        'title' => 'Internal Media Course',
        'slug' => 'internal-media-course',
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
        'title' => 'Internal Media Lesson',
        'slug' => 'internal-media-lesson',
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

    $storagePath = 'tenants/'.$tenant->id.'/lessons/internal-media-lesson.mp4';
    Storage::disk(config('filesystems.default'))->put($storagePath, 'video-body');

    LessonMedia::query()->create([
        'tenant_id' => $tenant->id,
        'lesson_id' => $lesson->id,
        'media_type' => 'video',
        'provider' => 'internal',
        'provider_ref' => 'internal-media-lesson',
        'url' => null,
        'content' => null,
        'duration_seconds' => 600,
        'sort_order' => 1,
        'is_active' => true,
        'metadata' => [
            'storage_disk' => config('filesystems.default'),
            'storage_path' => $storagePath,
        ],
    ]);

    $response = $this->getJson("/api/v1/learning/lessons/{$lesson->id}", $headers);

    $response->assertSuccessful()
        ->assertJsonPath('data.can_access', true)
        ->assertJsonPath('data.media.0.provider', 'internal')
        ->assertJsonPath('data.media.0.url_kind', 'temporary')
        ->assertJsonPath('data.media.0.url_expires_at', now()->addMinutes(5)->toIso8601String());

    expect($response->json('data.media.0.url'))
        ->toBeString()
        ->toContain('/storage/');

    Date::setTestNow();
});

it('uses metadata player_url for embed lesson media when available', function (): void {
    $tenant = makeTenant(['domain' => 'tenant-a.local']);
    [$student, $headers] = actingAsUserType(UserType::Student, $tenant);

    $course = Course::factory()->for($tenant)->create([
        'price_cents' => 10000,
        'status' => 'published',
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
        'title' => 'Embed Player Lesson',
        'slug' => 'embed-player-lesson',
        'status' => 'published',
        'sort_order' => 1,
        'is_free' => false,
    ]);

    Enrollment::factory()->for($tenant)->for($student)->for($course)->active()->create();

    LessonMedia::query()->create([
        'tenant_id' => $tenant->id,
        'lesson_id' => $lesson->id,
        'media_type' => 'video',
        'provider' => 'embed',
        'provider_ref' => 'embed-player',
        'url' => 'https://video.example/fallback',
        'duration_seconds' => 420,
        'sort_order' => 1,
        'is_active' => true,
        'metadata' => [
            'player_url' => 'https://player.example/embed-player',
        ],
    ]);

    $this->getJson("/api/v1/learning/lessons/{$lesson->id}", $headers)
        ->assertSuccessful()
        ->assertJsonPath('data.media.0.provider', 'embed')
        ->assertJsonPath('data.media.0.url', 'https://player.example/embed-player')
        ->assertJsonPath('data.media.0.url_kind', 'player')
        ->assertJsonPath('data.media.0.url_expires_at', null);
});

it('builds a canonical player url for vimeo lesson media from provider_ref', function (): void {
    $tenant = makeTenant(['domain' => 'tenant-a.local']);
    [$student, $headers] = actingAsUserType(UserType::Student, $tenant);

    $course = Course::factory()->for($tenant)->create([
        'price_cents' => 10000,
        'status' => 'published',
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
        'title' => 'Vimeo Lesson',
        'slug' => 'vimeo-lesson',
        'status' => 'published',
        'sort_order' => 1,
        'is_free' => false,
    ]);

    Enrollment::factory()->for($tenant)->for($student)->for($course)->active()->create();

    LessonMedia::query()->create([
        'tenant_id' => $tenant->id,
        'lesson_id' => $lesson->id,
        'media_type' => 'video',
        'provider' => 'vimeo',
        'provider_ref' => '123456789',
        'url' => null,
        'duration_seconds' => 420,
        'sort_order' => 1,
        'is_active' => true,
        'metadata' => [],
    ]);

    $this->getJson("/api/v1/learning/lessons/{$lesson->id}", $headers)
        ->assertSuccessful()
        ->assertJsonPath('data.media.0.provider', 'vimeo')
        ->assertJsonPath('data.media.0.url', 'https://player.vimeo.com/video/123456789')
        ->assertJsonPath('data.media.0.provider_config.video_id', '123456789')
        ->assertJsonPath('data.media.0.url_kind', 'player')
        ->assertJsonPath('data.media.0.url_expires_at', null);
});

it('shows paid lesson vitrine but denies content access for expired enrollment', function (): void {
    $tenant = makeTenant(['domain' => 'tenant-a.local']);
    [$student, $headers] = actingAsUserType(UserType::Student, $tenant);

    $course = Course::query()->create([
        'tenant_id' => $tenant->id,
        'title' => 'Expired Course',
        'slug' => 'expired-course',
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
        'title' => 'Expired Lesson',
        'slug' => 'expired-lesson',
        'status' => 'published',
        'sort_order' => 1,
        'is_free' => false,
    ]);

    Enrollment::query()->create([
        'tenant_id' => $tenant->id,
        'user_id' => $student->id,
        'course_id' => $course->id,
        'status' => 'expired',
        'enrolled_at' => now()->subDays(40),
        'access_expires_at' => now()->subDay(),
        'progress_percentage' => 80,
    ]);

    $this->getJson("/api/v1/learning/lessons/{$lesson->id}", $headers)
        ->assertSuccessful()
        ->assertJsonPath('data.id', $lesson->id)
        ->assertJsonPath('data.title', 'Expired Lesson')
        ->assertJsonPath('data.course.id', $course->id)
        ->assertJsonPath('data.module.id', $module->id)
        ->assertJsonPath('data.can_access', false)
        ->assertJsonPath('data.media', null)
        ->assertJsonPath('data.progress', null);

    $this->assertDatabaseMissing('lesson_views', [
        'tenant_id' => $tenant->id,
        'user_id' => $student->id,
        'lesson_id' => $lesson->id,
    ]);
});

it('records one lesson view per successful access', function (): void {
    $tenant = makeTenant(['domain' => 'tenant-a.local']);
    [$student, $headers] = actingAsUserType(UserType::Student, $tenant);

    $course = Course::query()->create([
        'tenant_id' => $tenant->id,
        'title' => 'Replay Course',
        'slug' => 'replay-course',
        'description' => 'Course description',
        'status' => 'published',
        'price_cents' => 0,
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
        'title' => 'Replay Lesson',
        'slug' => 'replay-lesson',
        'status' => 'published',
        'sort_order' => 1,
        'is_free' => true,
    ]);

    $this->getJson("/api/v1/learning/lessons/{$lesson->id}", $headers)->assertSuccessful();
    $this->getJson("/api/v1/learning/lessons/{$lesson->id}", $headers)->assertSuccessful();

    expect(LessonView::query()
        ->where('tenant_id', $tenant->id)
        ->where('user_id', $student->id)
        ->where('lesson_id', $lesson->id)
        ->count())->toBe(2);
});

it('forbids progress updates for paid lesson with expired enrollment', function (): void {
    $tenant = makeTenant(['domain' => 'tenant-a.local']);
    [$student, $headers] = actingAsUserType(UserType::Student, $tenant);

    $course = Course::query()->create([
        'tenant_id' => $tenant->id,
        'title' => 'Expired Progress Course',
        'slug' => 'expired-progress-course',
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
        'title' => 'Expired Progress Lesson',
        'slug' => 'expired-progress-lesson',
        'status' => 'published',
        'sort_order' => 1,
        'is_free' => false,
    ]);

    Enrollment::query()->create([
        'tenant_id' => $tenant->id,
        'user_id' => $student->id,
        'course_id' => $course->id,
        'status' => 'expired',
        'enrolled_at' => now()->subDays(40),
        'access_expires_at' => now()->subDay(),
        'progress_percentage' => 80,
    ]);

    $this->postJson("/api/v1/learning/lessons/{$lesson->id}/progress", [
        'time_spent_seconds' => 120,
        'current_time_seconds' => 60,
        'total_time_seconds' => 300,
        'progress_percentage' => 20,
        'is_completed' => false,
    ], $headers)->assertForbidden();
});

it('updates lesson progress', function (): void {
    $tenant = makeTenant(['domain' => 'tenant-a.local']);
    [$student, $headers] = actingAsUserType(UserType::Student, $tenant);

    $course = Course::query()->create([
        'tenant_id' => $tenant->id,
        'title' => 'Progress Course',
        'slug' => 'progress-course',
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
        'title' => 'Lesson 1',
        'slug' => 'lesson-1',
        'status' => 'published',
        'sort_order' => 1,
        'is_free' => false,
    ]);

    $enrollment = Enrollment::query()->create([
        'tenant_id' => $tenant->id,
        'user_id' => $student->id,
        'course_id' => $course->id,
        'status' => 'active',
        'enrolled_at' => now(),
        'access_expires_at' => now()->addDays(30),
        'progress_percentage' => 0,
    ]);

    $this->postJson("/api/v1/learning/lessons/{$lesson->id}/progress", [
        'time_spent_seconds' => 120,
        'current_time_seconds' => 60,
        'total_time_seconds' => 300,
        'progress_percentage' => 20,
        'is_completed' => false,
    ], $headers)
        ->assertSuccessful()
        ->assertJsonPath('data.time_spent_seconds', 120)
        ->assertJsonPath('data.is_completed', false);

    $this->assertDatabaseHas('lesson_progress', [
        'tenant_id' => $tenant->id,
        'user_id' => $student->id,
        'course_id' => $course->id,
        'enrollment_id' => $enrollment->id,
        'lesson_id' => $lesson->id,
        'time_spent_seconds' => 120,
        'is_completed' => false,
    ]);
});

it('completes lesson progress from the lesson media strategy and stores media progress', function (): void {
    $tenant = makeTenant(['domain' => 'tenant-a.local']);
    [$student, $headers] = actingAsUserType(UserType::Student, $tenant);

    $course = Course::query()->create([
        'tenant_id' => $tenant->id,
        'title' => 'Strategy Course',
        'slug' => 'strategy-course',
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
        'title' => 'Lesson Strategy',
        'slug' => 'lesson-strategy',
        'status' => 'published',
        'sort_order' => 1,
        'is_free' => false,
    ]);

    $enrollment = Enrollment::query()->create([
        'tenant_id' => $tenant->id,
        'user_id' => $student->id,
        'course_id' => $course->id,
        'status' => 'active',
        'enrolled_at' => now(),
        'access_expires_at' => now()->addDays(30),
        'progress_percentage' => 0,
    ]);

    $lessonMedia = LessonMedia::query()->create([
        'tenant_id' => $tenant->id,
        'lesson_id' => $lesson->id,
        'media_type' => 'video',
        'provider' => 'youtube',
        'provider_ref' => 'strategy-video',
        'duration_seconds' => 300,
        'progress_strategy' => '80_percent',
        'sort_order' => 1,
        'is_active' => true,
    ]);

    $this->postJson("/api/v1/learning/lessons/{$lesson->id}/progress", [
        'time_spent_seconds' => 240,
        'current_time_seconds' => 240,
        'total_time_seconds' => 300,
        'progress_percentage' => 80,
        'is_completed' => false,
    ], $headers)
        ->assertSuccessful()
        ->assertJsonPath('data.progress_percentage', 100)
        ->assertJsonPath('data.is_completed', true);

    $this->assertDatabaseHas('lesson_progress', [
        'tenant_id' => $tenant->id,
        'user_id' => $student->id,
        'course_id' => $course->id,
        'enrollment_id' => $enrollment->id,
        'lesson_id' => $lesson->id,
        'progress_percentage' => 100,
        'is_completed' => 1,
    ]);

    $this->assertDatabaseHas('lesson_media_progress', [
        'tenant_id' => $tenant->id,
        'user_id' => $student->id,
        'lesson_media_id' => $lessonMedia->id,
        'watched_seconds' => 240,
        'completion_percentage' => 100.00,
        'is_completed' => 1,
    ]);

    expect($enrollment->refresh()->progress_percentage)->toBe(100);
});

it('uses metadata required_seconds for time based lesson media progress', function (): void {
    $tenant = makeTenant(['domain' => 'tenant-a.local']);
    [$student, $headers] = actingAsUserType(UserType::Student, $tenant);

    $course = Course::query()->create([
        'tenant_id' => $tenant->id,
        'title' => 'Time Based Course',
        'slug' => 'time-based-course',
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
        'title' => 'Time Based Lesson',
        'slug' => 'time-based-lesson',
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

    $lessonMedia = LessonMedia::query()->create([
        'tenant_id' => $tenant->id,
        'lesson_id' => $lesson->id,
        'media_type' => 'video',
        'provider' => 's3',
        'provider_ref' => 'time-based-video',
        'duration_seconds' => 600,
        'progress_strategy' => 'time_based',
        'sort_order' => 1,
        'is_active' => true,
        'metadata' => [
            'storage_path' => 'tenants/'.$tenant->id.'/lessons/time-based.mp4',
            'required_seconds' => 180,
        ],
    ]);

    $this->postJson("/api/v1/learning/lessons/{$lesson->id}/progress", [
        'time_spent_seconds' => 179,
        'current_time_seconds' => 179,
        'total_time_seconds' => 600,
        'progress_percentage' => 29,
        'is_completed' => false,
    ], $headers)
        ->assertSuccessful()
        ->assertJsonPath('data.progress_percentage', 30)
        ->assertJsonPath('data.is_completed', false);

    $this->postJson("/api/v1/learning/lessons/{$lesson->id}/progress", [
        'time_spent_seconds' => 180,
        'current_time_seconds' => 180,
        'total_time_seconds' => 600,
        'progress_percentage' => 30,
        'is_completed' => false,
    ], $headers)
        ->assertSuccessful()
        ->assertJsonPath('data.progress_percentage', 100)
        ->assertJsonPath('data.is_completed', true);

    $this->assertDatabaseHas('lesson_media_progress', [
        'tenant_id' => $tenant->id,
        'user_id' => $student->id,
        'lesson_media_id' => $lessonMedia->id,
        'watched_seconds' => 180,
        'completion_percentage' => 100.00,
        'is_completed' => 1,
    ]);
});

it('marks lesson as completed and updates enrollment progress', function (): void {
    $tenant = makeTenant(['domain' => 'tenant-a.local']);
    [$student, $headers] = actingAsUserType(UserType::Student, $tenant);

    $course = Course::query()->create([
        'tenant_id' => $tenant->id,
        'title' => 'Complete Course',
        'slug' => 'complete-course',
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
        'title' => 'Lesson 1',
        'slug' => 'lesson-1',
        'status' => 'published',
        'sort_order' => 1,
        'is_free' => false,
    ]);

    $enrollment = Enrollment::query()->create([
        'tenant_id' => $tenant->id,
        'user_id' => $student->id,
        'course_id' => $course->id,
        'status' => 'active',
        'enrolled_at' => now(),
        'access_expires_at' => now()->addDays(30),
        'progress_percentage' => 0,
    ]);

    $this->postJson("/api/v1/learning/lessons/{$lesson->id}/progress", [
        'time_spent_seconds' => 300,
        'current_time_seconds' => 300,
        'total_time_seconds' => 300,
        'progress_percentage' => 100,
        'is_completed' => true,
    ], $headers)
        ->assertSuccessful()
        ->assertJsonPath('data.is_completed', true);

    $enrollment->refresh();
    expect($enrollment->progress_percentage)->toBe(100);
    expect($enrollment->status)->toBe('active');
    expect($enrollment->completed_at)->toBeNull();
});

it('updates progress on the current enrollment when historical rows exist', function (): void {
    $tenant = makeTenant(['domain' => 'tenant-a.local']);
    [$student, $headers] = actingAsUserType(UserType::Student, $tenant);

    $course = Course::query()->create([
        'tenant_id' => $tenant->id,
        'title' => 'Historical Progress Course',
        'slug' => 'historical-progress-course',
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
        'title' => 'Lesson 1',
        'slug' => 'lesson-1',
        'status' => 'published',
        'sort_order' => 1,
        'is_free' => false,
    ]);

    $historicalEnrollment = Enrollment::query()->create([
        'tenant_id' => $tenant->id,
        'user_id' => $student->id,
        'course_id' => $course->id,
        'status' => 'cancelled',
        'enrolled_at' => now()->subDays(20),
        'progress_percentage' => 20,
    ]);

    $currentEnrollment = Enrollment::query()->create([
        'tenant_id' => $tenant->id,
        'user_id' => $student->id,
        'course_id' => $course->id,
        'status' => 'active',
        'enrolled_at' => now()->subDay(),
        'access_expires_at' => now()->addDays(30),
        'progress_percentage' => 0,
    ]);

    $this->postJson("/api/v1/learning/lessons/{$lesson->id}/progress", [
        'time_spent_seconds' => 300,
        'current_time_seconds' => 300,
        'total_time_seconds' => 300,
        'progress_percentage' => 100,
        'is_completed' => true,
    ], $headers)
        ->assertSuccessful();

    expect($currentEnrollment->refresh()->progress_percentage)->toBe(100);
    expect($currentEnrollment->refresh()->status)->toBe('active');
    expect($historicalEnrollment->refresh()->progress_percentage)->toBe(20);
});

it('keeps lesson completion immutable after it has been reached', function (): void {
    Date::setTestNow('2026-07-08 12:00:00');

    $tenant = makeTenant(['domain' => 'tenant-a.local']);
    [$student, $headers] = actingAsUserType(UserType::Student, $tenant);

    $course = Course::query()->create([
        'tenant_id' => $tenant->id,
        'title' => 'Immutable Completion Course',
        'slug' => 'immutable-completion-course',
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
        'title' => 'Completion Lesson',
        'slug' => 'completion-lesson',
        'status' => 'published',
        'sort_order' => 1,
        'is_free' => false,
    ]);

    $enrollment = Enrollment::query()->create([
        'tenant_id' => $tenant->id,
        'user_id' => $student->id,
        'course_id' => $course->id,
        'status' => 'active',
        'enrolled_at' => now(),
        'access_expires_at' => now()->addDays(30),
        'progress_percentage' => 0,
    ]);

    $lessonMedia = LessonMedia::query()->create([
        'tenant_id' => $tenant->id,
        'lesson_id' => $lesson->id,
        'media_type' => 'video',
        'provider' => 's3',
        'provider_ref' => 'immutable-media',
        'duration_seconds' => 300,
        'progress_strategy' => '80_percent',
        'sort_order' => 1,
        'is_active' => true,
        'metadata' => [
            'storage_disk' => 's3',
            'storage_path' => 'tenants/'.$tenant->id.'/lessons/immutable-media.mp4',
        ],
    ]);

    $this->postJson("/api/v1/learning/lessons/{$lesson->id}/progress", [
        'lesson_media_id' => $lessonMedia->id,
        'time_spent_seconds' => 240,
        'current_time_seconds' => 240,
        'total_time_seconds' => 300,
        'progress_percentage' => 80,
        'is_completed' => false,
    ], $headers)
        ->assertSuccessful()
        ->assertJsonPath('data.is_completed', true);

    $completedProgress = LessonProgress::query()
        ->where('tenant_id', $tenant->id)
        ->where('user_id', $student->id)
        ->where('lesson_id', $lesson->id)
        ->firstOrFail();
    $completedProgressCompletedAt = $completedProgress->completed_at?->toIso8601String();

    $completedMediaProgress = LessonMediaProgress::query()
        ->where('tenant_id', $tenant->id)
        ->where('user_id', $student->id)
        ->where('lesson_media_id', $lessonMedia->id)
        ->firstOrFail();
    $completedMediaProgressCompletedAt = $completedMediaProgress->completed_at?->toIso8601String();

    Date::setTestNow('2026-07-08 13:00:00');

    $this->postJson("/api/v1/learning/lessons/{$lesson->id}/progress", [
        'lesson_media_id' => $lessonMedia->id,
        'time_spent_seconds' => 10,
        'current_time_seconds' => 10,
        'total_time_seconds' => 300,
        'progress_percentage' => 5,
        'is_completed' => false,
    ], $headers)
        ->assertSuccessful()
        ->assertJsonPath('data.is_completed', true);

    expect(LessonProgress::query()
        ->where('tenant_id', $tenant->id)
        ->where('user_id', $student->id)
        ->where('lesson_id', $lesson->id)
        ->firstOrFail()->is_completed)->toBeTrue()
        ->and(LessonProgress::query()
            ->where('tenant_id', $tenant->id)
            ->where('user_id', $student->id)
            ->where('lesson_id', $lesson->id)
            ->firstOrFail()->completed_at?->toIso8601String())->toBe($completedProgressCompletedAt);

    expect(LessonMediaProgress::query()
        ->where('tenant_id', $tenant->id)
        ->where('user_id', $student->id)
        ->where('lesson_media_id', $lessonMedia->id)
        ->firstOrFail()->is_completed)->toBeTrue()
        ->and(LessonMediaProgress::query()
            ->where('tenant_id', $tenant->id)
            ->where('user_id', $student->id)
            ->where('lesson_media_id', $lessonMedia->id)
            ->firstOrFail()->completed_at?->toIso8601String())->toBe($completedMediaProgressCompletedAt);

    Date::setTestNow();
});

it('uses the explicit lesson media when multiple active medias exist', function (): void {
    $tenant = makeTenant(['domain' => 'tenant-a.local']);
    [$student, $headers] = actingAsUserType(UserType::Student, $tenant);

    $course = Course::query()->create([
        'tenant_id' => $tenant->id,
        'title' => 'Multi Media Course',
        'slug' => 'multi-media-course',
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
        'title' => 'Multi Media Lesson',
        'slug' => 'multi-media-lesson',
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

    $primaryMedia = LessonMedia::query()->create([
        'tenant_id' => $tenant->id,
        'lesson_id' => $lesson->id,
        'media_type' => 'video',
        'provider' => 'embed',
        'provider_ref' => 'primary-media',
        'url' => 'https://video.example/primary',
        'duration_seconds' => 300,
        'progress_strategy' => '80_percent',
        'sort_order' => 1,
        'is_active' => true,
    ]);

    $targetMedia = LessonMedia::query()->create([
        'tenant_id' => $tenant->id,
        'lesson_id' => $lesson->id,
        'media_type' => 'video',
        'provider' => 's3',
        'provider_ref' => 'target-media',
        'duration_seconds' => 600,
        'progress_strategy' => 'time_based',
        'sort_order' => 2,
        'is_active' => true,
        'metadata' => [
            'storage_disk' => 's3',
            'storage_path' => 'tenants/'.$tenant->id.'/lessons/target-media.mp4',
            'required_seconds' => 60,
        ],
    ]);

    $this->postJson("/api/v1/learning/lessons/{$lesson->id}/progress", [
        'lesson_media_id' => $targetMedia->id,
        'time_spent_seconds' => 60,
        'current_time_seconds' => 60,
        'total_time_seconds' => 600,
        'progress_percentage' => 10,
        'is_completed' => false,
    ], $headers)
        ->assertSuccessful()
        ->assertJsonPath('data.is_completed', true);

    $this->assertDatabaseHas('lesson_media_progress', [
        'tenant_id' => $tenant->id,
        'user_id' => $student->id,
        'lesson_media_id' => $targetMedia->id,
        'is_completed' => 1,
    ]);

    $this->assertDatabaseMissing('lesson_media_progress', [
        'tenant_id' => $tenant->id,
        'user_id' => $student->id,
        'lesson_media_id' => $primaryMedia->id,
    ]);
});

it('uses the only active lesson media when progress is reported without an explicit target', function (): void {
    $tenant = makeTenant(['domain' => 'tenant-a.local']);
    [$student, $headers] = actingAsUserType(UserType::Student, $tenant);

    $course = Course::query()->create([
        'tenant_id' => $tenant->id,
        'title' => 'Single Active Media Course',
        'slug' => 'single-active-media-course',
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
        'title' => 'Single Active Lesson',
        'slug' => 'single-active-lesson',
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

    $activeMedia = LessonMedia::query()->create([
        'tenant_id' => $tenant->id,
        'lesson_id' => $lesson->id,
        'media_type' => 'video',
        'provider' => 's3',
        'provider_ref' => 'active-media',
        'duration_seconds' => 600,
        'progress_strategy' => 'time_based',
        'sort_order' => 1,
        'is_active' => true,
        'metadata' => [
            'storage_disk' => 's3',
            'storage_path' => 'tenants/'.$tenant->id.'/lessons/active-media.mp4',
            'required_seconds' => 60,
        ],
    ]);

    LessonMedia::query()->create([
        'tenant_id' => $tenant->id,
        'lesson_id' => $lesson->id,
        'media_type' => 'video',
        'provider' => 'embed',
        'provider_ref' => 'inactive-media',
        'url' => 'https://video.example/inactive',
        'duration_seconds' => 300,
        'progress_strategy' => '80_percent',
        'sort_order' => 2,
        'is_active' => false,
    ]);

    $this->postJson("/api/v1/learning/lessons/{$lesson->id}/progress", [
        'time_spent_seconds' => 60,
        'current_time_seconds' => 60,
        'total_time_seconds' => 600,
        'progress_percentage' => 10,
        'is_completed' => false,
    ], $headers)
        ->assertSuccessful()
        ->assertJsonPath('data.is_completed', true);

    $this->assertDatabaseHas('lesson_media_progress', [
        'tenant_id' => $tenant->id,
        'user_id' => $student->id,
        'lesson_media_id' => $activeMedia->id,
        'is_completed' => 1,
    ]);
});

it('allows an instructor to delete a lesson in the current tenant', function (): void {
    $tenant = makeTenant(['domain' => 'tenant-a.local']);
    [, $headers] = actingAsUserType(UserType::Instructor, $tenant);

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

    $lesson = Lesson::query()->create([
        'tenant_id' => $tenant->id,
        'course_module_id' => $module->id,
        'title' => 'Lesson A',
        'slug' => 'lesson-a',
        'status' => 'published',
        'sort_order' => 1,
        'is_free' => true,
    ]);

    $this->deleteJson('/api/v1/learning/lessons/'.$lesson->id, [], $headers)
        ->assertOk()
        ->assertJsonPath('message', 'Lesson deleted successfully.');

    $this->assertSoftDeleted('lessons', ['id' => $lesson->id]);
});

it('returns 403 for a student without permission when deleting a lesson', function (): void {
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

    $lesson = Lesson::query()->create([
        'tenant_id' => $tenant->id,
        'course_module_id' => $module->id,
        'title' => 'Lesson A',
        'slug' => 'lesson-a',
        'status' => 'published',
        'sort_order' => 1,
        'is_free' => true,
    ]);

    $this->deleteJson('/api/v1/learning/lessons/'.$lesson->id, [], $headers)
        ->assertForbidden();
});

it('returns 404 for a missing or foreign lesson when deleting', function (): void {
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

    $module = CourseModule::query()->create([
        'tenant_id' => $tenantB->id,
        'course_id' => $course->id,
        'title' => 'Module B',
        'sort_order' => 1,
    ]);

    $foreignLesson = Lesson::query()->create([
        'tenant_id' => $tenantB->id,
        'course_module_id' => $module->id,
        'title' => 'Foreign lesson',
        'slug' => 'foreign-lesson',
        'status' => 'published',
        'sort_order' => 1,
        'is_free' => true,
    ]);

    $this->deleteJson('/api/v1/learning/lessons/999999', [], $headers)
        ->assertNotFound();

    $this->deleteJson('/api/v1/learning/lessons/'.$foreignLesson->id, [], $headers)
        ->assertNotFound();
});

it('returns 401 without authentication when deleting a lesson', function (): void {
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

    $lesson = Lesson::query()->create([
        'tenant_id' => $tenant->id,
        'course_module_id' => $module->id,
        'title' => 'Lesson A',
        'slug' => 'lesson-a',
        'status' => 'published',
        'sort_order' => 1,
        'is_free' => true,
    ]);

    $this->deleteJson('/api/v1/learning/lessons/'.$lesson->id, [], tenantHeaders($tenant))
        ->assertUnauthorized();
});

it('allows an instructor to update a lesson title in the current tenant', function (): void {
    $tenant = makeTenant(['domain' => 'tenant-a.local']);
    [, $headers] = actingAsUserType(UserType::Instructor, $tenant);

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

    $lesson = Lesson::query()->create([
        'tenant_id' => $tenant->id,
        'course_module_id' => $module->id,
        'title' => 'Old title',
        'slug' => 'lesson-a',
        'status' => 'published',
        'sort_order' => 1,
        'is_free' => true,
    ]);

    $this->patchJson('/api/v1/learning/lessons/'.$lesson->id, [
        'title' => 'Updated title',
    ], $headers)
        ->assertOk()
        ->assertJsonPath('data.id', $lesson->id)
        ->assertJsonPath('data.title', 'Updated title')
        ->assertJsonPath('data.sort_order', 1)
        ->assertJsonPath('data.is_free', true);

    expect($lesson->refresh()->title)->toBe('Updated title');
});

it('returns 403 for a student without permission when updating a lesson', function (): void {
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

    $lesson = Lesson::query()->create([
        'tenant_id' => $tenant->id,
        'course_module_id' => $module->id,
        'title' => 'Old title',
        'slug' => 'lesson-a',
        'status' => 'published',
        'sort_order' => 1,
        'is_free' => true,
    ]);

    $this->patchJson('/api/v1/learning/lessons/'.$lesson->id, [
        'title' => 'Updated title',
    ], $headers)
        ->assertForbidden();
});

it('returns 404 for a missing or foreign lesson when updating', function (): void {
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

    $module = CourseModule::query()->create([
        'tenant_id' => $tenantB->id,
        'course_id' => $course->id,
        'title' => 'Module B',
        'sort_order' => 1,
    ]);

    $foreignLesson = Lesson::query()->create([
        'tenant_id' => $tenantB->id,
        'course_module_id' => $module->id,
        'title' => 'Foreign lesson',
        'slug' => 'foreign-lesson',
        'status' => 'published',
        'sort_order' => 1,
        'is_free' => true,
    ]);

    $this->patchJson('/api/v1/learning/lessons/999999', [
        'title' => 'Updated title',
    ], $headers)->assertNotFound();

    $this->patchJson('/api/v1/learning/lessons/'.$foreignLesson->id, [
        'title' => 'Updated title',
    ], $headers)->assertNotFound();
});

it('returns 401 without authentication when updating a lesson', function (): void {
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

    $lesson = Lesson::query()->create([
        'tenant_id' => $tenant->id,
        'course_module_id' => $module->id,
        'title' => 'Old title',
        'slug' => 'lesson-a',
        'status' => 'published',
        'sort_order' => 1,
        'is_free' => true,
    ]);

    $this->patchJson('/api/v1/learning/lessons/'.$lesson->id, [
        'title' => 'Updated title',
    ], tenantHeaders($tenant))
        ->assertUnauthorized();
});

it('returns 422 for invalid lesson update payload', function (): void {
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
        'title' => 'Module A',
        'sort_order' => 1,
    ]);

    $lesson = Lesson::query()->create([
        'tenant_id' => $tenant->id,
        'course_module_id' => $module->id,
        'title' => 'Old title',
        'slug' => 'lesson-a',
        'status' => 'published',
        'sort_order' => 1,
        'is_free' => true,
    ]);

    $this->patchJson('/api/v1/learning/lessons/'.$lesson->id, [], $headers)
        ->assertStatus(422)
        ->assertJsonPath('errors.0.code', 'validation_error');
});
