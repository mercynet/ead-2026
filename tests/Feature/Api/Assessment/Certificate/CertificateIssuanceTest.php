<?php

use App\Modules\Assessment\Actions\Certificate\IssueCertificateAction;
use App\Modules\Assessment\Models\Certificate;
use App\Modules\Assessment\Models\Questionnaire;
use App\Modules\Assessment\Models\QuizAttempt;
use App\Modules\Core\Models\Tenant;
use App\Modules\Core\Models\User;
use App\Modules\Learning\Actions\Lesson\UpdateProgressAction;
use App\Modules\Learning\Events\CourseCompletedEvent;
use App\Modules\Learning\Models\Course;
use App\Modules\Learning\Models\CourseModule;
use App\Modules\Learning\Models\Enrollment;
use App\Modules\Learning\Models\Lesson;
use App\Shared\Http\ApiContext;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;

uses(RefreshDatabase::class);

/**
 * @param  array<string, mixed>  $courseAttributes
 * @return array{tenant: Tenant, student: User, course: Course, module: CourseModule, lesson: Lesson, enrollment: Enrollment}
 */
function setupCertificateCourse(array $courseAttributes = []): array
{
    $tenant = Tenant::factory()->create();

    $student = User::factory()->for($tenant)->create();

    seedRbac();
    $student->assignRole('student');

    $course = Course::factory()->for($tenant)->create(array_merge([
        'certificate_enabled' => true,
        'certificate_min_progress' => 100,
        'certificate_requires_quiz' => false,
        'certificate_min_score' => 70,
    ], $courseAttributes));

    $module = CourseModule::factory()->for($tenant)->for($course)->create();

    $lesson = Lesson::factory()->for($tenant)->for($module)->create();

    $enrollment = Enrollment::factory()
        ->for($tenant)
        ->for($student)
        ->for($course)
        ->active()
        ->create();

    return [
        'tenant' => $tenant,
        'student' => $student,
        'course' => $course,
        'module' => $module,
        'lesson' => $lesson,
        'enrollment' => $enrollment,
    ];
}

function completeCourseAsStudent(Tenant $tenant, User $student, Lesson $lesson): \Illuminate\Testing\TestResponse
{
    $token = $student->createToken('test-token')->plainTextToken;

    return test()->postJson("/api/v1/learning/lessons/{$lesson->id}/progress", [
        'time_spent_seconds' => 300,
        'current_time_seconds' => 300,
        'total_time_seconds' => 300,
        'progress_percentage' => 100,
        'is_completed' => true,
    ], [
        'Authorization' => 'Bearer '.$token,
        'X-Tenant-ID' => (string) $tenant->id,
    ]);
}

function createCourseQuestionnaire(Tenant $tenant, Course $course): Questionnaire
{
    return Questionnaire::factory()
        ->for($tenant)
        ->course()
        ->create([
            'quizable_type' => $course->getMorphClass(),
            'quizable_id' => $course->id,
        ]);
}

it('issues certificate automatically when course is completed', function (): void {
    $data = setupCertificateCourse();
    extract($data);

    completeCourseAsStudent($tenant, $student, $lesson)->assertSuccessful();

    $certificate = Certificate::query()
        ->where('tenant_id', $tenant->id)
        ->where('enrollment_id', $enrollment->id)
        ->first();

    expect($certificate)->not->toBeNull()
        ->and($certificate->user_id)->toBe($student->id)
        ->and($certificate->course_id)->toBe($course->id)
        ->and($certificate->status)->toBe('issued')
        ->and($certificate->issued_at)->not->toBeNull()
        ->and($certificate->certificate_number)->toMatch('/^CERT-\d{4}-[0-9A-F]{8}$/');
});

it('does not issue certificate when certificate_enabled is false', function (): void {
    $data = setupCertificateCourse(['certificate_enabled' => false]);
    extract($data);

    completeCourseAsStudent($tenant, $student, $lesson)->assertSuccessful();

    expect(Certificate::query()->where('enrollment_id', $enrollment->id)->exists())->toBeFalse();
});

it('does not issue certificate when quiz is required and no passing attempt exists', function (): void {
    $data = setupCertificateCourse(['certificate_requires_quiz' => true]);
    extract($data);

    createCourseQuestionnaire($tenant, $course);

    completeCourseAsStudent($tenant, $student, $lesson)->assertSuccessful();

    expect(Certificate::query()->where('enrollment_id', $enrollment->id)->exists())->toBeFalse();
});

it('issues certificate when quiz is required and attempt passed with sufficient score', function (): void {
    $data = setupCertificateCourse([
        'certificate_requires_quiz' => true,
        'certificate_min_score' => 70,
    ]);
    extract($data);

    $questionnaire = createCourseQuestionnaire($tenant, $course);

    QuizAttempt::factory()
        ->for($tenant)
        ->for($student)
        ->for($questionnaire)
        ->completed()
        ->create(['score' => 85, 'passed' => true]);

    completeCourseAsStudent($tenant, $student, $lesson)->assertSuccessful();

    expect(Certificate::query()->where('enrollment_id', $enrollment->id)->where('status', 'issued')->count())->toBe(1);
});

it('does not issue certificate when passing attempt score is below certificate_min_score', function (): void {
    $data = setupCertificateCourse([
        'certificate_requires_quiz' => true,
        'certificate_min_score' => 80,
    ]);
    extract($data);

    $questionnaire = createCourseQuestionnaire($tenant, $course);

    QuizAttempt::factory()
        ->for($tenant)
        ->for($student)
        ->for($questionnaire)
        ->completed()
        ->create(['score' => 75, 'passed' => true]);

    completeCourseAsStudent($tenant, $student, $lesson)->assertSuccessful();

    expect(Certificate::query()->where('enrollment_id', $enrollment->id)->exists())->toBeFalse();
});

it('does not duplicate certificate when one was already issued for the enrollment', function (): void {
    $data = setupCertificateCourse();
    extract($data);

    Certificate::factory()
        ->for($tenant)
        ->for($student)
        ->for($enrollment)
        ->create([
            'course_id' => $course->id,
            'status' => 'issued',
        ]);

    completeCourseAsStudent($tenant, $student, $lesson)->assertSuccessful();

    expect(Certificate::query()->where('enrollment_id', $enrollment->id)->count())->toBe(1);
});

it('enforces a single certificate per enrollment at the database level', function (): void {
    $data = setupCertificateCourse();
    extract($data);

    Certificate::factory()
        ->for($tenant)
        ->for($student)
        ->for($enrollment)
        ->create(['course_id' => $course->id, 'status' => 'issued']);

    expect(fn () => Certificate::factory()
        ->for($tenant)
        ->for($student)
        ->for($enrollment)
        ->create(['course_id' => $course->id, 'status' => 'issued'])
    )->toThrow(UniqueConstraintViolationException::class);
});

it('recovers from a unique constraint violation during issuance without throwing', function (): void {
    $data = setupCertificateCourse();
    extract($data);

    $enrollment->update(['progress_percentage' => 100]);

    Certificate::factory()
        ->for($tenant)
        ->for($student)
        ->for($enrollment)
        ->revoked()
        ->create(['course_id' => $course->id]);

    $result = app(IssueCertificateAction::class)->handle(
        new CourseCompletedEvent($enrollment->refresh(), $student, $course)
    );

    expect($result)->toBeNull()
        ->and(Certificate::query()->where('enrollment_id', $enrollment->id)->count())->toBe(1)
        ->and(Certificate::query()->where('enrollment_id', $enrollment->id)->where('status', 'issued')->exists())->toBeFalse();
});

it('does not dispatch completion events when an outer transaction rolls back', function (): void {
    $data = setupCertificateCourse();
    extract($data);

    Event::fake([CourseCompletedEvent::class]);

    $context = new ApiContext($student, $tenant);

    try {
        DB::transaction(function () use ($context, $lesson): void {
            app(UpdateProgressAction::class)->handle($context, $lesson, [
                'time_spent_seconds' => 300,
                'current_time_seconds' => 300,
                'total_time_seconds' => 300,
                'progress_percentage' => 100,
                'is_completed' => true,
            ]);

            throw new RuntimeException('outer failure after progress update');
        });
    } catch (RuntimeException) {
    }

    Event::assertNotDispatched(CourseCompletedEvent::class);

    expect($enrollment->refresh()->completed_at)->toBeNull()
        ->and(Certificate::query()->where('enrollment_id', $enrollment->id)->exists())->toBeFalse();
});

it('keeps the progress request successful when certificate issuance fails', function (): void {
    $data = setupCertificateCourse();
    extract($data);

    $this->mock(IssueCertificateAction::class)
        ->shouldReceive('handle')
        ->once()
        ->andThrow(new RuntimeException('issuance exploded'));

    completeCourseAsStudent($tenant, $student, $lesson)->assertSuccessful();

    expect($enrollment->refresh()->completed_at)->not->toBeNull()
        ->and($enrollment->progress_percentage)->toBe(100)
        ->and(Certificate::query()->where('enrollment_id', $enrollment->id)->exists())->toBeFalse();
});
