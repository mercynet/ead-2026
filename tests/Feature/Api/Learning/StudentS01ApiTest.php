<?php

use App\Modules\Core\Enums\UserType;
use App\Modules\Learning\Events\LessonViewedEvent;
use App\Modules\Learning\Models\Course;
use App\Modules\Learning\Models\CourseMaterial;
use App\Modules\Learning\Models\CourseModule;
use App\Modules\Learning\Models\Enrollment;
use App\Modules\Learning\Models\Lesson;
use App\Modules\Learning\Models\LessonMedia;
use App\Modules\Learning\Models\LessonProgress;
use App\Modules\Learning\Models\LessonView;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;

function makeStudentS01Course(\App\Modules\Core\Models\Tenant $tenant, array $attributes = []): Course
{
    return Course::factory()->for($tenant)->create(array_merge([
        'status' => 'published',
        'is_active' => true,
        'price_cents' => 1500,
    ], $attributes));
}

function makeStudentS01Lesson(Course $course, array $attributes = []): Lesson
{
    $module = CourseModule::factory()->for($course->tenant)->for($course)->create();

    return Lesson::factory()->for($course->tenant)->for($module)->create(array_merge([
        'status' => 'published',
        'is_active' => true,
        'content' => ['body' => 'Conteúdo pedagógico'],
    ], $attributes));
}

it('lists only the authenticated student active consumable courses', function (): void {
    $tenant = makeTenant();
    $otherTenant = makeTenant();
    [$student, $headers] = actingAsUserType(UserType::Student, $tenant);
    [$otherStudent] = actingAsUserType(UserType::Student, $tenant);

    $activeCourse = makeStudentS01Course($tenant);
    $pendingCourse = makeStudentS01Course($tenant);
    $expiredCourse = makeStudentS01Course($tenant);
    $cancelledCourse = makeStudentS01Course($tenant);
    $draftCourse = makeStudentS01Course($tenant, ['status' => 'draft', 'published_at' => null]);
    $inactiveCourse = makeStudentS01Course($tenant, ['is_active' => false]);
    $foreignCourse = makeStudentS01Course($otherTenant);

    Enrollment::factory()->active()->for($tenant)->for($activeCourse)->for($student, 'user')->create();
    Enrollment::factory()->pending()->for($tenant)->for($pendingCourse)->for($student, 'user')->create();
    Enrollment::factory()->expired()->for($tenant)->for($expiredCourse)->for($student, 'user')->create();
    Enrollment::factory()->cancelled()->for($tenant)->for($cancelledCourse)->for($student, 'user')->create();
    Enrollment::factory()->active()->for($tenant)->for($draftCourse)->for($student, 'user')->create();
    Enrollment::factory()->active()->for($tenant)->for($inactiveCourse)->for($student, 'user')->create();
    Enrollment::factory()->active()->for($otherTenant)->for($foreignCourse)->for($otherStudent, 'user')->create();

    $response = $this->getJson('/api/v1/student/courses', $headers)
        ->assertSuccessful()
        ->assertJsonPath('data.0.id', $activeCourse->id)
        ->assertJsonStructure(['data', 'links', 'meta']);

    expect($response->json('data'))->toHaveCount(1)
        ->and(array_keys($response->json('data.0')))->toBe([
            'id', 'title', 'slug', 'description', 'short_description', 'thumbnail', 'banner',
            'level', 'duration_hours', 'is_free', 'categories', 'enrollment',
        ])
        ->and($response->json('data.0'))->not->toHaveKey('tenant_id')
        ->not->toHaveKey('instructor_id')
        ->not->toHaveKey('price_cents')
        ->not->toHaveKey('status');
});

it('requires an active non-expired own enrollment for course consumption', function (): void {
    $tenant = makeTenant();
    [$student, $headers] = actingAsUserType(UserType::Student, $tenant);
    $states = [
        'pending' => Enrollment::factory()->pending(),
        'expired' => Enrollment::factory()->expired(),
        'cancelled' => Enrollment::factory()->cancelled(),
    ];

    foreach ($states as $state => $factory) {
        $course = makeStudentS01Course($tenant, ['title' => 'Course '.$state, 'slug' => 'course-'.$state]);
        $lesson = makeStudentS01Lesson($course, ['is_free' => false]);
        $factory->for($tenant)->for($course)->for($student, 'user')->create();

        assertApiErrorEnvelope($this->getJson("/api/v1/student/courses/{$course->id}", $headers), 404, 'not_found');
        assertApiErrorEnvelope($this->getJson("/api/v1/student/lessons/{$lesson->id}", $headers), 404, 'not_found');
        assertApiErrorEnvelope($this->postJson("/api/v1/student/lessons/{$lesson->id}/progress", [
            'time_spent_seconds' => 10,
            'current_time_seconds' => 10,
            'total_time_seconds' => 100,
            'progress_percentage' => 100,
            'is_completed' => true,
        ], $headers), 404, 'not_found');
    }

    $expiredActiveCourse = makeStudentS01Course($tenant, ['title' => 'Expired active', 'slug' => 'expired-active']);
    $expiredActiveLesson = makeStudentS01Lesson($expiredActiveCourse, ['is_free' => false]);
    Enrollment::factory()->for($tenant)->for($expiredActiveCourse)->for($student, 'user')->create([
        'status' => 'active',
        'access_expires_at' => now()->subSecond(),
    ]);
    assertApiErrorEnvelope($this->getJson("/api/v1/student/courses/{$expiredActiveCourse->id}", $headers), 404, 'not_found');
    assertApiErrorEnvelope($this->getJson("/api/v1/student/lessons/{$expiredActiveLesson->id}", $headers), 404, 'not_found');

    $activeCourse = makeStudentS01Course($tenant, ['title' => 'Active Course', 'slug' => 'active-course']);
    $activeLesson = makeStudentS01Lesson($activeCourse, ['is_free' => false]);
    $enrollment = Enrollment::factory()->active()->for($tenant)->for($activeCourse)->for($student, 'user')->create();

    $this->getJson("/api/v1/student/courses/{$activeCourse->id}", $headers)
        ->assertSuccessful()
        ->assertJsonPath('data.enrollment.id', $enrollment->id)
        ->assertJsonMissingPath('data.enrollment.user_id');
    $this->getJson("/api/v1/student/lessons/{$activeLesson->id}", $headers)
        ->assertSuccessful()
        ->assertJsonPath('data.access_mode', 'enrolled');

    $this->postJson("/api/v1/student/lessons/{$activeLesson->id}/progress", [
        'time_spent_seconds' => 10,
        'current_time_seconds' => 10,
        'total_time_seconds' => 100,
        'progress_percentage' => 10,
        'is_completed' => false,
    ], $headers)
        ->assertSuccessful()
        ->assertJsonPath('data.lesson_id', $activeLesson->id)
        ->assertJsonPath('data.progress_percentage', 10)
        ->assertJsonMissingPath('data.user_id');

    expect(LessonProgress::query()
        ->where('tenant_id', $tenant->id)
        ->where('user_id', $student->id)
        ->where('lesson_id', $activeLesson->id)
        ->exists())->toBeTrue();
});

it('isolates student A from student B and from a foreign tenant', function (): void {
    $tenant = makeTenant();
    $otherTenant = makeTenant();
    [$studentA, $headersA] = actingAsUserType(UserType::Student, $tenant);
    [$studentB] = actingAsUserType(UserType::Student, $tenant);
    $courseB = makeStudentS01Course($tenant, ['title' => 'B Course', 'slug' => 'b-course']);
    $lessonB = makeStudentS01Lesson($courseB, ['is_free' => false]);
    $materialB = CourseMaterial::factory()->for($tenant)->for($courseB)->create([
        'file_path' => 'tenants/'.$tenant->id.'/materials/b.pdf',
    ]);
    LessonMedia::factory()->for($tenant)->for($lessonB)->create();
    Enrollment::factory()->active()->for($tenant)->for($courseB)->for($studentB, 'user')->create();

    expect($this->getJson('/api/v1/student/courses', $headersA)->json('data'))->toBe([]);
    assertApiErrorEnvelope($this->getJson("/api/v1/student/courses/{$courseB->id}", $headersA), 404, 'not_found');
    assertApiErrorEnvelope($this->getJson("/api/v1/student/lessons/{$lessonB->id}", $headersA), 404, 'not_found');
    assertApiErrorEnvelope($this->getJson("/api/v1/student/lessons/{$lessonB->id}/media", $headersA), 404, 'not_found');
    assertApiErrorEnvelope($this->getJson("/api/v1/student/courses/{$courseB->id}/materials", $headersA), 404, 'not_found');
    assertApiErrorEnvelope($this->postJson("/api/v1/student/courses/{$courseB->id}/materials/{$materialB->id}/downloads", [], $headersA), 404, 'not_found');

    $foreignCourse = makeStudentS01Course($otherTenant, ['title' => 'Foreign', 'slug' => 'foreign']);
    $foreignLesson = makeStudentS01Lesson($foreignCourse, ['is_free' => false]);

    assertApiErrorEnvelope($this->getJson("/api/v1/student/courses/{$foreignCourse->id}", $headersA), 404, 'not_found');
    assertApiErrorEnvelope($this->getJson("/api/v1/student/lessons/{$foreignLesson->id}", $headersA), 404, 'not_found');
    $wrongTenantHeaders = $headersA;
    $wrongTenantHeaders['X-Tenant-ID'] = (string) $otherTenant->id;
    assertApiErrorEnvelope($this->getJson('/api/v1/student/courses', $wrongTenantHeaders), 403, 'access_denied');
});

it('navigates only visible modules and lessons with cursor pagination', function (): void {
    $tenant = makeTenant();
    [$student, $headers] = actingAsUserType(UserType::Student, $tenant);
    $course = makeStudentS01Course($tenant);
    Enrollment::factory()->active()->for($tenant)->for($course)->for($student, 'user')->create();
    $visibleModule = CourseModule::factory()->for($tenant)->for($course)->create(['sort_order' => 1]);
    $hiddenModule = CourseModule::factory()->for($tenant)->for($course)->create(['sort_order' => 2]);
    $visibleLesson = Lesson::factory()->for($tenant)->for($visibleModule)->create([
        'status' => 'published', 'is_active' => true, 'sort_order' => 1,
    ]);
    Lesson::factory()->for($tenant)->for($visibleModule)->create([
        'status' => 'draft', 'is_active' => true, 'sort_order' => 2,
    ]);
    Lesson::factory()->for($tenant)->for($visibleModule)->create([
        'status' => 'published', 'is_active' => false, 'sort_order' => 3,
    ]);
    Lesson::factory()->for($tenant)->for($hiddenModule)->create([
        'status' => 'draft', 'is_active' => true,
    ]);
    $inactiveLesson = Lesson::factory()->for($tenant)->for($visibleModule)->create([
        'status' => 'published', 'is_active' => false,
    ]);
    $draftLesson = Lesson::factory()->for($tenant)->for($visibleModule)->create([
        'status' => 'draft', 'is_active' => true,
    ]);

    $this->getJson("/api/v1/student/courses/{$course->id}/modules", $headers)
        ->assertSuccessful()
        ->assertJsonPath('data.0.id', $visibleModule->id)
        ->assertJsonCount(1, 'data')
        ->assertJsonStructure(['data', 'links', 'meta']);
    $this->getJson("/api/v1/student/courses/{$course->id}/modules/{$visibleModule->id}/lessons", $headers)
        ->assertSuccessful()
        ->assertJsonPath('data.0.id', $visibleLesson->id)
        ->assertJsonCount(1, 'data')
        ->assertJsonStructure(['data', 'links', 'meta']);
    assertApiErrorEnvelope($this->getJson("/api/v1/student/lessons/{$inactiveLesson->id}", $headers), 404, 'not_found');
    assertApiErrorEnvelope($this->getJson("/api/v1/student/lessons/{$draftLesson->id}", $headers), 404, 'not_found');
    assertApiErrorEnvelope($this->getJson("/api/v1/student/courses/{$course->id}/modules/{$hiddenModule->id}/lessons", $headers), 404, 'not_found');
});

it('keeps the My Courses query count bounded as the dataset grows', function (): void {
    $tenant = makeTenant();
    [$student, $headers] = actingAsUserType(UserType::Student, $tenant);

    $createCourse = function () use ($tenant, $student): void {
        $course = makeStudentS01Course($tenant);
        Enrollment::factory()->active()->for($tenant)->for($course)->for($student, 'user')->create();
    };

    $createCourse();
    DB::flushQueryLog();
    DB::enableQueryLog();
    $this->getJson('/api/v1/student/courses', $headers)->assertSuccessful();
    $smallCount = count(DB::getQueryLog());
    DB::disableQueryLog();

    foreach (range(1, 5) as $ignored) {
        $createCourse();
    }
    DB::flushQueryLog();
    DB::enableQueryLog();
    $this->getJson('/api/v1/student/courses', $headers)->assertSuccessful();
    $largeCount = count(DB::getQueryLog());
    DB::disableQueryLog();

    expect($largeCount)->toBeLessThanOrEqual($smallCount + 1);
});

it('projects lesson content and active media without internal storage metadata', function (): void {
    Event::fake([LessonViewedEvent::class]);
    $tenant = makeTenant();
    [$student, $headers] = actingAsUserType(UserType::Student, $tenant);
    $course = makeStudentS01Course($tenant);
    Enrollment::factory()->active()->for($tenant)->for($course)->for($student, 'user')->create();
    $lesson = makeStudentS01Lesson($course, [
        'content' => ['body' => 'Texto da aula'],
        'description' => 'Descrição pedagógica',
    ]);
    LessonMedia::factory()->for($tenant)->for($lesson)->create([
        'provider' => 'embed',
        'url' => 'https://video.example/player',
        'metadata' => [
            'storage_path' => 'tenants/'.$tenant->id.'/private/secret.mp4',
            'storage_disk' => 'local',
            'required_seconds' => 60,
        ],
    ]);
    LessonMedia::factory()->for($tenant)->for($lesson)->create(['is_active' => false]);

    $response = $this->getJson("/api/v1/student/lessons/{$lesson->id}", $headers)
        ->assertSuccessful()
        ->assertJsonPath('data.content.body', 'Texto da aula')
        ->assertJsonPath('data.media.0.url', 'https://video.example/player')
        ->assertJsonMissingPath('data.video_path')
        ->assertJsonMissingPath('data.media.0.provider_ref')
        ->assertJsonMissingPath('data.media.0.metadata')
        ->assertJsonMissingPath('data.media.0.provider_config')
        ->assertJsonMissingPath('data.media.0.storage_path');

    expect($response->json('data.media'))->toHaveCount(1);
    Event::assertDispatched(LessonViewedEvent::class);
    expect(LessonView::query()->where('lesson_id', $lesson->id)->count())->toBe(1);
});

it('allows a free lesson preview without persistence or material access', function (): void {
    Event::fake([LessonViewedEvent::class]);
    $tenant = makeTenant();
    [$student, $headers] = actingAsUserType(UserType::Student, $tenant);
    $course = makeStudentS01Course($tenant, ['price_cents' => 0]);
    $lesson = makeStudentS01Lesson($course, ['is_free' => true]);
    LessonMedia::factory()->for($tenant)->for($lesson)->create(['provider' => 'embed']);
    $material = CourseMaterial::factory()->for($tenant)->for($course)->create([
        'file_path' => 'tenants/'.$tenant->id.'/materials/preview.pdf',
    ]);

    $this->getJson("/api/v1/student/lessons/{$lesson->id}", $headers)
        ->assertSuccessful()
        ->assertJsonPath('data.access_mode', 'preview')
        ->assertJsonPath('data.progress', null);
    assertApiErrorEnvelope($this->getJson("/api/v1/student/courses/{$course->id}/materials", $headers), 404, 'not_found');
    assertApiErrorEnvelope($this->postJson("/api/v1/student/courses/{$course->id}/materials/{$material->id}/downloads", [], $headers), 404, 'not_found');

    Event::assertNotDispatched(LessonViewedEvent::class);
    expect(LessonView::query()->where('lesson_id', $lesson->id)->count())->toBe(0);
});

it('downloads a material only after access and returns no storage path', function (): void {
    $tenant = makeTenant();
    [$student, $headers] = actingAsUserType(UserType::Student, $tenant);
    $course = makeStudentS01Course($tenant);
    Enrollment::factory()->active()->for($tenant)->for($course)->for($student, 'user')->create();
    $material = CourseMaterial::factory()->for($tenant)->for($course)->create([
        'file_path' => 'tenants/'.$tenant->id.'/materials/handout.pdf',
    ]);
    Storage::disk(config('filesystems.default'))->put($material->file_path, 'material');

    $response = $this->postJson("/api/v1/student/courses/{$course->id}/materials/{$material->id}/downloads", [], $headers)
        ->assertCreated()
        ->assertJsonPath('data.course_material_id', $material->id)
        ->assertJsonMissingPath('data.user_id')
        ->assertJsonMissingPath('data.file_path');

    expect($response->json('data.download_url'))->toBeString()->not->toBe('')
        ->and($response->json('data'))->not->toHaveKey('storage_path');
    $this->assertDatabaseHas('material_downloads', [
        'tenant_id' => $tenant->id,
        'course_material_id' => $material->id,
        'user_id' => $student->id,
    ]);
});

it('returns 401 and area_forbidden on the Student surface', function (): void {
    $tenant = makeTenant();
    $course = makeStudentS01Course($tenant);

    assertApiErrorEnvelope($this->getJson('/api/v1/student/courses', tenantHeaders($tenant)), 401, 'unauthenticated');
    [, $adminHeaders] = actingAsUserType(UserType::Admin, $tenant);
    assertApiErrorEnvelope($this->getJson("/api/v1/student/courses/{$course->id}", $adminHeaders), 403, 'area_forbidden');
});
