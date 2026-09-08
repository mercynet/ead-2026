<?php

use App\Modules\Core\Enums\UserType;
use App\Modules\Learning\Events\CourseCompletedEvent;
use App\Modules\Learning\Events\LessonCompletedEvent;
use App\Modules\Learning\Models\Course;
use App\Modules\Learning\Models\CourseModule;
use App\Modules\Learning\Models\Enrollment;
use App\Modules\Learning\Models\Lesson;
use App\Modules\Learning\Models\LessonProgress;
use Illuminate\Support\Facades\Event;

it('keeps course progress own, tenant-scoped and monotonic across replay', function (): void {
    Event::fake([CourseCompletedEvent::class, LessonCompletedEvent::class]);

    $tenant = makeTenant();
    $foreignTenant = makeTenant();
    [$student, $headers] = actingAsUserType(UserType::Student, $tenant);
    [$otherStudent] = actingAsUserType(UserType::Student, $tenant);

    $course = Course::factory()->for($tenant)->create([
        'status' => 'published',
        'is_active' => true,
    ]);
    $module = CourseModule::factory()->for($tenant)->for($course)->create();
    $lesson = Lesson::factory()->for($tenant)->for($module)->create([
        'status' => 'published',
        'is_active' => true,
    ]);
    $draft = Lesson::factory()->for($tenant)->for($module)->create([
        'status' => 'draft',
        'is_active' => true,
    ]);
    $inactive = Lesson::factory()->for($tenant)->for($module)->create([
        'status' => 'published',
        'is_active' => false,
    ]);
    $foreignModule = CourseModule::factory()->for($foreignTenant)->for($course)->create();
    $foreignLesson = Lesson::factory()->for($foreignTenant)->for($foreignModule)->create([
        'status' => 'published',
        'is_active' => true,
    ]);
    $enrollment = Enrollment::factory()->active()->for($tenant)->for($course)->for($student, 'user')->create();

    LessonProgress::factory()->completed()->for($tenant)->for($student, 'user')->for($course)->for($enrollment)->for($foreignLesson)->create();
    LessonProgress::factory()->completed()->for($tenant)->for($otherStudent, 'user')->for($course)->for($enrollment)->for($draft)->create();

    $response = $this->postJson("/api/v1/student/lessons/{$lesson->id}/progress", [
        'time_spent_seconds' => 100,
        'current_time_seconds' => 100,
        'total_time_seconds' => 100,
        'progress_percentage' => 100,
        'is_completed' => true,
    ], $headers)->assertCreated();

    $enrollment->refresh();

    expect($response->json('data.is_completed'))->toBeTrue()
        ->and($enrollment->progress_percentage)->toBe(100)
        ->and($enrollment->completed_at)->not->toBeNull()
        ->and(LessonProgress::query()->where('lesson_id', $lesson->id)->where('user_id', $student->id)->count())->toBe(1);

    Event::assertDispatchedTimes(LessonCompletedEvent::class, 1);
    Event::assertDispatchedTimes(CourseCompletedEvent::class, 1);

    $this->postJson("/api/v1/student/lessons/{$lesson->id}/progress", [
        'time_spent_seconds' => 10,
        'current_time_seconds' => 10,
        'total_time_seconds' => 10,
        'progress_percentage' => 10,
        'is_completed' => false,
    ], $headers)->assertSuccessful();

    $progress = LessonProgress::query()->where('lesson_id', $lesson->id)->where('user_id', $student->id)->sole();
    $enrollment->refresh();

    expect($progress->progress_percentage)->toBe(100)
        ->and($progress->isCompleted())->toBeTrue()
        ->and($enrollment->progress_percentage)->toBe(100);
    Event::assertDispatchedTimes(LessonCompletedEvent::class, 1);
    Event::assertDispatchedTimes(CourseCompletedEvent::class, 1);
});
