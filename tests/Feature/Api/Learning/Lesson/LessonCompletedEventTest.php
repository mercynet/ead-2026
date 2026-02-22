<?php

use App\Events\Learning\LessonCompletedEvent;
use App\Models\Course;
use App\Models\CourseModule;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\LessonProgress;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

function setupStudentWithCourse(): array
{
    $tenant = Tenant::factory()->create();

    $student = User::factory()->for($tenant)->create();

    Permission::query()->firstOrCreate(['name' => 'learning.lesson.view', 'guard_name' => 'web']);
    Permission::query()->firstOrCreate(['name' => 'learning.lesson.progress', 'guard_name' => 'web']);
    Role::query()->firstOrCreate(['name' => 'student', 'guard_name' => 'web'])
        ->givePermissionTo(['learning.lesson.view', 'learning.lesson.progress']);
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

it('dispatches LessonCompletedEvent when lesson is marked as completed', function (): void {
    Event::fake([LessonCompletedEvent::class]);

    $data = setupStudentWithCourse();
    extract($data);

    $token = $student->createToken('test-token')->plainTextToken;

    $this->postJson("/api/v1/learning/lessons/{$lesson->id}/progress", [
        'time_spent_seconds' => 300,
        'current_time_seconds' => 300,
        'total_time_seconds' => 300,
        'progress_percentage' => 100,
        'is_completed' => true,
    ], [
        'Authorization' => 'Bearer '.$token,
        'X-Tenant-ID' => (string) $tenant->id,
    ])->assertSuccessful();

    Event::assertDispatched(LessonCompletedEvent::class, function ($event) use ($lesson, $student, $course): bool {
        return $event->lesson->id === $lesson->id
            && $event->user->id === $student->id
            && $event->course->id === $course->id;
    });
});

it('does not dispatch LessonCompletedEvent when lesson is not completed', function (): void {
    Event::fake([LessonCompletedEvent::class]);

    $data = setupStudentWithCourse();
    extract($data);

    $token = $student->createToken('test-token')->plainTextToken;

    $this->postJson("/api/v1/learning/lessons/{$lesson->id}/progress", [
        'time_spent_seconds' => 120,
        'current_time_seconds' => 60,
        'total_time_seconds' => 300,
        'progress_percentage' => 20,
        'is_completed' => false,
    ], [
        'Authorization' => 'Bearer '.$token,
        'X-Tenant-ID' => (string) $tenant->id,
    ])->assertSuccessful();

    Event::assertNotDispatched(LessonCompletedEvent::class);
});

it('does not dispatch LessonCompletedEvent when lesson was already completed', function (): void {
    Event::fake([LessonCompletedEvent::class]);

    $data = setupStudentWithCourse();
    extract($data);

    LessonProgress::factory()
        ->for($tenant)
        ->for($student)
        ->for($course)
        ->for($enrollment)
        ->for($lesson)
        ->completed()
        ->create();

    $token = $student->createToken('test-token')->plainTextToken;

    $this->postJson("/api/v1/learning/lessons/{$lesson->id}/progress", [
        'time_spent_seconds' => 400,
        'current_time_seconds' => 300,
        'total_time_seconds' => 300,
        'progress_percentage' => 100,
        'is_completed' => true,
    ], [
        'Authorization' => 'Bearer '.$token,
        'X-Tenant-ID' => (string) $tenant->id,
    ])->assertSuccessful();

    Event::assertNotDispatched(LessonCompletedEvent::class);
});
