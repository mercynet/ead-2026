<?php

use App\Modules\Core\Models\Tenant;
use App\Modules\Core\Models\User;
use App\Modules\Learning\Events\CourseCompletedEvent;
use App\Modules\Learning\Models\Course;
use App\Modules\Learning\Models\CourseModule;
use App\Modules\Learning\Models\Enrollment;
use App\Modules\Learning\Models\Lesson;
use App\Modules\Learning\Models\LessonProgress;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;

uses(RefreshDatabase::class);

/**
 * @return array{tenant: Tenant, student: User, course: Course, module: CourseModule, lesson: Lesson, enrollment: Enrollment}
 */
function setupStudentWithSingleLessonCourse(): array
{
    $tenant = Tenant::factory()->create();

    $student = User::factory()->for($tenant)->create();

    seedRbac();
    $student->assignRole('student');

    $course = Course::factory()->for($tenant)->create();

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

function completeLessonRequest(Tenant $tenant, User $student, Lesson $lesson): \Illuminate\Testing\TestResponse
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

it('dispatches CourseCompletedEvent when enrollment reaches 100%', function (): void {
    Event::fake([CourseCompletedEvent::class]);

    $data = setupStudentWithSingleLessonCourse();
    extract($data);

    completeLessonRequest($tenant, $student, $lesson)->assertSuccessful();

    Event::assertDispatched(CourseCompletedEvent::class, function ($event) use ($enrollment, $student, $course): bool {
        return $event->enrollment->id === $enrollment->id
            && $event->user->id === $student->id
            && $event->course->id === $course->id
            && (int) $event->enrollment->progress_percentage === 100;
    });
});

it('does not dispatch CourseCompletedEvent when course is not fully completed', function (): void {
    Event::fake([CourseCompletedEvent::class]);

    $data = setupStudentWithSingleLessonCourse();
    extract($data);

    Lesson::factory()->for($tenant)->for($module)->create();

    completeLessonRequest($tenant, $student, $lesson)->assertSuccessful();

    Event::assertNotDispatched(CourseCompletedEvent::class);
});

it('does not dispatch CourseCompletedEvent again when enrollment was already completed', function (): void {
    Event::fake([CourseCompletedEvent::class]);

    $data = setupStudentWithSingleLessonCourse();
    extract($data);

    LessonProgress::factory()
        ->for($tenant)
        ->for($student)
        ->for($course)
        ->for($enrollment)
        ->for($lesson)
        ->completed()
        ->create();

    $enrollment->update([
        'progress_percentage' => 100,
        'completed_at' => now()->subDay(),
    ]);

    completeLessonRequest($tenant, $student, $lesson)->assertSuccessful();

    Event::assertNotDispatched(CourseCompletedEvent::class);
});
