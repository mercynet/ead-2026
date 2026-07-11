<?php

use App\Modules\Core\Models\Tenant;
use App\Modules\Core\Models\User;
use App\Modules\Learning\Events\LessonViewedEvent;
use App\Modules\Learning\Models\Course;
use App\Modules\Learning\Models\CourseModule;
use App\Modules\Learning\Models\Enrollment;
use App\Modules\Learning\Models\Lesson;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;

uses(RefreshDatabase::class);

function setupStudentWithPublishedLesson(): array
{
    $tenant = Tenant::factory()->create();

    $student = User::factory()->for($tenant)->create();

    seedRbac();
    $student->assignRole('student');

    $course = Course::factory()->for($tenant)->create([
        'status' => 'published',
        'price_cents' => 10000,
    ]);

    $module = CourseModule::factory()->for($tenant)->for($course)->create();

    $lesson = Lesson::factory()->for($tenant)->for($module)->create([
        'status' => 'published',
        'is_free' => false,
    ]);

    Enrollment::factory()
        ->for($tenant)
        ->for($student)
        ->for($course)
        ->active()
        ->create();

    return [
        'tenant' => $tenant,
        'student' => $student,
        'course' => $course,
        'lesson' => $lesson,
    ];
}

it('dispatches LessonViewedEvent when an accessible lesson is shown', function (): void {
    Event::fake([LessonViewedEvent::class]);

    $data = setupStudentWithPublishedLesson();
    extract($data);

    $token = $student->createToken('test-token')->plainTextToken;

    $this->getJson("/api/v1/learning/lessons/{$lesson->id}", [
        'Authorization' => 'Bearer '.$token,
        'X-Tenant-ID' => (string) $tenant->id,
    ])->assertSuccessful();

    Event::assertDispatched(LessonViewedEvent::class, function (LessonViewedEvent $event) use ($lesson, $student, $course): bool {
        return $event->lesson->id === $lesson->id
            && $event->user->id === $student->id
            && $event->course->id === $course->id
            && $event->view->lesson_id === $lesson->id;
    });
});
