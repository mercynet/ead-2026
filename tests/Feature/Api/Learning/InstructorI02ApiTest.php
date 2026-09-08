<?php

use App\Modules\Core\Enums\UserType;
use App\Modules\Core\Models\TenantCustomization;
use App\Modules\Core\Models\User;
use App\Modules\Financial\Models\Order;
use App\Modules\Financial\Models\OrderItem;
use App\Modules\Financial\Models\Payment;
use App\Modules\Learning\Events\EnrollmentCreatedEvent;
use App\Modules\Learning\Models\Course;
use App\Modules\Learning\Models\CourseMaterial;
use App\Modules\Learning\Models\CourseModule;
use App\Modules\Learning\Models\Enrollment;
use App\Modules\Learning\Models\Lesson;
use App\Modules\Learning\Models\LessonMedia;
use App\Modules\Learning\Models\LessonProgress;
use Illuminate\Support\Facades\Event;

it('exposes the instructor-owned roster endpoint', function (): void {
    $tenant = makeTenant();
    [$instructor, $headers] = actingAsUserType(UserType::Instructor, $tenant);
    $course = Course::factory()->for($tenant)->create(['instructor_id' => $instructor->id]);
    $student = \App\Modules\Core\Models\User::factory()->forTenant($tenant)->student()->create();
    Enrollment::factory()->active()->for($tenant)->for($course)->for($student, 'user')->create();

    $this->getJson('/api/v1/instructor/enrollments', $headers)
        ->assertSuccessful()
        ->assertJsonPath('data.0.course_id', $course->id);
});

it('exposes the instructor-owned free enrollment endpoint', function (): void {
    $tenant = makeTenant();
    [$instructor, $headers] = actingAsUserType(UserType::Instructor, $tenant);
    TenantCustomization::query()->create([
        'tenant_id' => $tenant->id,
        'published_settings' => [
            'learning' => ['enrollments' => ['manual_free_by_instructor' => true]],
        ],
    ]);
    $course = Course::factory()->for($tenant)->create([
        'instructor_id' => $instructor->id,
        'price_cents' => 0,
        'status' => 'published',
        'is_active' => true,
    ]);
    $student = \App\Modules\Core\Models\User::factory()->forTenant($tenant)->student()->create();

    $this->postJson('/api/v1/instructor/enrollments', [
        'course_id' => $course->id,
        'user_id' => $student->id,
    ], $headers)->assertCreated();
});

it('isolates the roster by instructor-owned courses and projects only minimum student PII', function (): void {
    $tenant = makeTenant();
    [$instructor, $headers] = actingAsUserType(UserType::Instructor, $tenant);
    [$otherInstructor] = actingAsUserType(UserType::Instructor, $tenant);
    $otherTenant = makeTenant();
    $ownCourse = Course::factory()->for($tenant)->create(['instructor_id' => $instructor->id]);
    $otherCourse = Course::factory()->for($tenant)->create(['instructor_id' => $otherInstructor->id]);
    $adminCourse = Course::factory()->for($tenant)->create(['instructor_id' => null]);
    $foreignCourse = Course::factory()->for($otherTenant)->create(['instructor_id' => $instructor->id]);
    $student = User::factory()->forTenant($tenant)->student()->create(['avatar' => 'avatar.png']);

    foreach ([$ownCourse, $otherCourse, $adminCourse, $foreignCourse] as $course) {
        Enrollment::factory()->active()->for($course)->for($student, 'user')->create(['tenant_id' => $course->tenant_id]);
    }

    $response = $this->getJson('/api/v1/instructor/enrollments', $headers)
        ->assertSuccessful()
        ->assertJsonCount(1, 'data');

    expect($response->json('data.0.course_id'))->toBe($ownCourse->id)
        ->and(array_keys($response->json('data.0.user')))->toBe(['id', 'name', 'avatar'])
        ->and($response->json('data.0.user'))->not->toHaveKey('email')
        ->not->toHaveKey('tenant_id')
        ->and($response->json('data.0'))->not->toHaveKey('billing_type')
        ->not->toHaveKey('tenant_id');
});

it('returns defensive 404 for foreign owner enrollment and progress', function (): void {
    $tenant = makeTenant();
    [$instructor, $headers] = actingAsUserType(UserType::Instructor, $tenant);
    [$otherInstructor] = actingAsUserType(UserType::Instructor, $tenant);
    $otherCourse = Course::factory()->for($tenant)->create(['instructor_id' => $otherInstructor->id]);
    $student = User::factory()->forTenant($tenant)->student()->create();
    $enrollment = Enrollment::factory()->active()->for($tenant)->for($otherCourse)->for($student, 'user')->create();

    assertApiErrorEnvelope($this->getJson("/api/v1/instructor/enrollments/{$enrollment->id}", $headers), 404, 'not_found');
    assertApiErrorEnvelope($this->getJson("/api/v1/instructor/enrollments/{$enrollment->id}/progress", $headers), 404, 'not_found');
});

it('returns progress using only published active lessons and the minimum lesson detail', function (): void {
    $tenant = makeTenant();
    [$instructor, $headers] = actingAsUserType(UserType::Instructor, $tenant);
    $course = Course::factory()->for($tenant)->create(['instructor_id' => $instructor->id]);
    $module = CourseModule::factory()->for($tenant)->for($course)->create();
    $published = Lesson::factory()->for($tenant)->for($module)->create(['status' => 'published', 'is_active' => true]);
    $secondPublished = Lesson::factory()->for($tenant)->for($module)->create(['status' => 'published', 'is_active' => true]);
    Lesson::factory()->for($tenant)->for($module)->create(['status' => 'draft', 'is_active' => true]);
    Lesson::factory()->for($tenant)->for($module)->create(['status' => 'published', 'is_active' => false]);
    $student = User::factory()->forTenant($tenant)->student()->create();
    $enrollment = Enrollment::factory()->active()->for($tenant)->for($course)->for($student, 'user')->create();
    LessonProgress::factory()->completed()->for($tenant)->for($student, 'user')->for($course)->for($enrollment)->for($published)->create(['time_spent_seconds' => 90]);
    LessonProgress::factory()->for($tenant)->for($student, 'user')->for($course)->for($enrollment)->for($secondPublished)->create([
        'progress_percentage' => 50,
        'is_completed' => false,
        'last_watched_at' => now(),
        'time_spent_seconds' => 45,
    ]);

    $response = $this->getJson("/api/v1/instructor/enrollments/{$enrollment->id}/progress", $headers)->assertSuccessful();

    expect($response->json('data.progress.percentage'))->toBe(50)
        ->and($response->json('data.progress.completed_lessons'))->toBe(1)
        ->and($response->json('data.progress.total_lessons'))->toBe(2)
        ->and($response->json('data.progress.lessons'))->toHaveCount(2)
        ->and(array_keys($response->json('data.progress.lessons.0')))->toBe([
            'lesson_id', 'progress_percentage', 'is_completed', 'completed_at', 'last_watched_at', 'time_spent_seconds',
        ]);
});

it('supports own LessonMedia and CourseMaterial metadata CRUD without crossing their boundary', function (): void {
    $tenant = makeTenant();
    [$instructor, $headers] = actingAsUserType(UserType::Instructor, $tenant);
    [$otherInstructor] = actingAsUserType(UserType::Instructor, $tenant);
    $course = Course::factory()->for($tenant)->create(['instructor_id' => $instructor->id]);
    $module = CourseModule::factory()->for($tenant)->for($course)->create();
    $lesson = Lesson::factory()->for($tenant)->for($module)->create();
    $otherCourse = Course::factory()->for($tenant)->create(['instructor_id' => $otherInstructor->id]);
    $otherModule = CourseModule::factory()->for($tenant)->for($otherCourse)->create();
    $otherLesson = Lesson::factory()->for($tenant)->for($otherModule)->create();
    $otherTenant = makeTenant();
    $foreignCourse = Course::factory()->for($otherTenant)->create(['instructor_id' => $instructor->id]);
    $foreignModule = CourseModule::factory()->for($otherTenant)->for($foreignCourse)->create();
    $foreignLesson = Lesson::factory()->for($otherTenant)->for($foreignModule)->create();
    $unownedCourse = Course::factory()->for($tenant)->create(['instructor_id' => null]);
    $unownedModule = CourseModule::factory()->for($tenant)->for($unownedCourse)->create();
    $unownedLesson = Lesson::factory()->for($tenant)->for($unownedModule)->create();

    assertApiErrorEnvelope($this->postJson("/api/v1/instructor/lessons/{$lesson->id}/media", [
        'lesson_id' => $otherLesson->id,
        'media_type' => 'video', 'provider' => 'embed', 'provider_ref' => 'spoofed-parent',
    ], $headers), 422, 'validation_error');
    $mediaResponse = $this->postJson("/api/v1/instructor/lessons/{$lesson->id}/media", [
        'media_type' => 'video',
        'provider' => 'embed',
        'provider_ref' => 'own-video',
        'url' => 'https://video.example/own',
    ], $headers)->assertCreated();
    $media = LessonMedia::query()->where('provider_ref', 'own-video')->firstOrFail();
    $this->postJson("/api/v1/instructor/lessons/{$lesson->id}/media", [
        'media_type' => 'text', 'provider' => 'embed', 'content' => 'second media',
    ], $headers)->assertCreated();
    $mediaResponse->assertJsonMissingPath('data.metadata');
    $this->getJson("/api/v1/instructor/lessons/{$lesson->id}/media", $headers)->assertJsonCount(2, 'data');
    $this->patchJson("/api/v1/instructor/lessons/{$lesson->id}/media/{$media->id}", ['content' => 'updated'], $headers)->assertSuccessful();
    $this->deleteJson("/api/v1/instructor/lessons/{$lesson->id}/media/{$media->id}", [], $headers)->assertSuccessful();
    assertApiErrorEnvelope($this->getJson("/api/v1/instructor/lessons/{$otherLesson->id}/media", $headers), 404, 'not_found');
    assertApiErrorEnvelope($this->getJson("/api/v1/instructor/lessons/{$foreignLesson->id}/media", $headers), 404, 'not_found');
    assertApiErrorEnvelope($this->getJson("/api/v1/instructor/lessons/{$unownedLesson->id}/media", $headers), 404, 'not_found');

    assertApiErrorEnvelope($this->postJson("/api/v1/instructor/courses/{$course->id}/materials", [
        'course_id' => $otherCourse->id,
        'file_path' => 'tenants/'.$tenant->id.'/materials/spoofed.pdf',
    ], $headers), 422, 'validation_error');
    $materialResponse = $this->postJson("/api/v1/instructor/courses/{$course->id}/materials", [
        'file_path' => 'tenants/'.$tenant->id.'/materials/own.pdf',
    ], $headers)->assertCreated();
    $material = CourseMaterial::query()->where('course_id', $course->id)->firstOrFail();
    $materialResponse->assertJsonMissingPath('data.file_path');
    $this->getJson("/api/v1/instructor/courses/{$course->id}/materials", $headers)->assertJsonPath('data.0.id', $material->id);
    $this->patchJson("/api/v1/instructor/courses/{$course->id}/materials/{$material->id}", [
        'file_path' => 'tenants/'.$tenant->id.'/materials/updated.pdf',
    ], $headers)->assertSuccessful();
    assertApiErrorEnvelope($this->getJson("/api/v1/instructor/courses/{$otherCourse->id}/materials", $headers), 404, 'not_found');
    assertApiErrorEnvelope($this->getJson("/api/v1/instructor/courses/{$unownedCourse->id}/materials", $headers), 404, 'not_found');
    $foreignMaterial = CourseMaterial::factory()->for($otherTenant)->for($foreignCourse)->create();
    assertApiErrorEnvelope($this->getJson("/api/v1/instructor/courses/{$foreignCourse->id}/materials/{$foreignMaterial->id}", $headers), 404, 'not_found');
});

it('creates a free enrollment idempotently and preserves the zero-consideration mirror', function (): void {
    $tenant = makeTenant();
    [$instructor, $headers] = actingAsUserType(UserType::Instructor, $tenant);
    TenantCustomization::query()->create([
        'tenant_id' => $tenant->id,
        'published_settings' => ['learning' => ['enrollments' => ['manual_free_by_instructor' => true]]],
    ]);
    $course = Course::factory()->for($tenant)->create([
        'instructor_id' => $instructor->id, 'price_cents' => 0, 'status' => 'published', 'is_active' => true,
    ]);
    $student = User::factory()->forTenant($tenant)->student()->create();
    $eventCount = 0;
    Event::listen(EnrollmentCreatedEvent::class, function () use (&$eventCount): void {
        $eventCount++;
    });

    $first = $this->postJson('/api/v1/instructor/enrollments', ['course_id' => $course->id, 'user_id' => $student->id], $headers)->assertCreated();
    $second = $this->postJson('/api/v1/instructor/enrollments', ['course_id' => $course->id, 'user_id' => $student->id], $headers)->assertOk();

    expect($second->json('data.id'))->toBe($first->json('data.id'))
        ->and(Enrollment::query()->count())->toBe(1)
        ->and(Order::query()->count())->toBe(1)
        ->and(OrderItem::query()->count())->toBe(1)
        ->and(Payment::query()->count())->toBe(1)
        ->and(Order::query()->sole()->status)->toBe('paid')
        ->and(Order::query()->sole()->origin_type)->toBe('direct')
        ->and(Order::query()->sole()->total_cents)->toBe(0)
        ->and(Payment::query()->sole()->gateway_slug)->toBe('free')
        ->and(Payment::query()->sole()->charge_state)->toBe('resolved')
        ->and($eventCount)->toBe(1);
});

it('derives pending approval, rejects paid and external requests without side effects', function (): void {
    $tenant = makeTenant();
    [$instructor, $headers] = actingAsUserType(UserType::Instructor, $tenant);
    TenantCustomization::query()->create([
        'tenant_id' => $tenant->id,
        'published_settings' => ['learning' => ['enrollments' => ['manual_free_by_instructor' => true, 'manual_free_requires_approval' => true]]],
    ]);
    $freeCourse = Course::factory()->for($tenant)->create(['instructor_id' => $instructor->id, 'price_cents' => 0, 'status' => 'published', 'is_active' => true]);
    $paidCourse = Course::factory()->for($tenant)->create(['instructor_id' => $instructor->id, 'price_cents' => 100, 'status' => 'published', 'is_active' => true]);
    $student = User::factory()->forTenant($tenant)->student()->create();

    $this->postJson('/api/v1/instructor/enrollments', ['course_id' => $freeCourse->id, 'user_id' => $student->id], $headers)
        ->assertCreated()->assertJsonPath('data.status', 'pending');
    $before = [Enrollment::query()->count(), Order::query()->count(), Payment::query()->count()];

    assertApiErrorEnvelope($this->postJson('/api/v1/instructor/enrollments', [
        'course_id' => $paidCourse->id, 'user_id' => $student->id,
    ], $headers), 422, 'validation_error');
    assertApiErrorEnvelope($this->postJson('/api/v1/instructor/enrollments', [
        'course_id' => $freeCourse->id, 'user_id' => $student->id, 'billing_type' => 'external',
    ], $headers), 422, 'validation_error');

    expect([Enrollment::query()->count(), Order::query()->count(), Payment::query()->count()])->toBe($before);
});

it('rejects tenant, owner, parent and lifecycle spoofing on the Instructor surface', function (): void {
    $tenant = makeTenant();
    $otherTenant = makeTenant();
    [$instructor, $headers] = actingAsUserType(UserType::Instructor, $tenant);
    [$otherInstructor] = actingAsUserType(UserType::Instructor, $tenant);
    $ownCourse = Course::factory()->for($tenant)->create(['instructor_id' => $instructor->id, 'price_cents' => 0, 'status' => 'published', 'is_active' => true]);
    $foreignCourse = Course::factory()->for($otherTenant)->create(['instructor_id' => $instructor->id, 'price_cents' => 0, 'status' => 'published', 'is_active' => true]);
    $otherCourse = Course::factory()->for($tenant)->create(['instructor_id' => $otherInstructor->id, 'price_cents' => 0, 'status' => 'published', 'is_active' => true]);
    $student = User::factory()->forTenant($tenant)->student()->create();
    $foreignStudent = User::factory()->forTenant($otherTenant)->student()->create();
    TenantCustomization::query()->create(['tenant_id' => $tenant->id, 'published_settings' => ['learning' => ['enrollments' => ['manual_free_by_instructor' => true]]]]);

    assertApiErrorEnvelope($this->postJson('/api/v1/instructor/enrollments', ['course_id' => $otherCourse->id, 'user_id' => $student->id], $headers), 404, 'not_found');
    assertApiErrorEnvelope($this->postJson('/api/v1/instructor/enrollments', ['course_id' => $foreignCourse->id, 'user_id' => $foreignStudent->id], $headers), 404, 'not_found');
    assertApiErrorEnvelope($this->postJson('/api/v1/instructor/enrollments', [
        'course_id' => $ownCourse->id, 'user_id' => $student->id, 'tenant_id' => $otherTenant->id, 'status' => 'cancelled',
    ], $headers), 422, 'validation_error');
    $this->getJson('/api/v1/instructor/enrollments', ['X-Tenant-ID' => (string) $otherTenant->id, 'Authorization' => $headers['Authorization']])
        ->assertForbidden();
});
