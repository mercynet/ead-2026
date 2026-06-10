<?php

use App\Modules\Core\Enums\UserType;
use App\Modules\Learning\Models\Course;
use App\Modules\Learning\Models\CourseModule;
use App\Modules\Learning\Models\Enrollment;
use App\Modules\Learning\Models\Lesson;
use Illuminate\Foundation\Testing\RefreshDatabase;

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
    expect($enrollment->status)->toBe('completed');
});
