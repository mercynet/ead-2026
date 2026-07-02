<?php

use App\Modules\Core\Models\Tenant;
use App\Modules\Core\Models\User;
use App\Modules\Learning\Models\Course;
use App\Modules\Learning\Models\CourseModule;
use App\Modules\Learning\Models\Enrollment;
use App\Modules\Learning\Models\Lesson;
use App\Modules\Learning\Models\LessonProgress;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

it('returns course modules with lesson progress from the current enrollment', function (): void {
    $tenant = Tenant::query()->create([
        'name' => 'Tenant A',
        'domain' => 'tenant-a.local',
        'database' => null,
        'is_active' => true,
    ]);

    $student = User::query()->create([
        'tenant_id' => $tenant->id,
        'name' => 'Student',
        'email' => 'student@tenant-a.test',
        'password' => Hash::make('password123'),
    ]);

    Permission::query()->firstOrCreate(['name' => 'learning.courses.view', 'guard_name' => 'web']);
    Role::query()->firstOrCreate(['name' => 'student', 'guard_name' => 'web'])
        ->givePermissionTo('learning.courses.view');
    $student->assignRole('student');

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

    $lesson1 = Lesson::query()->create([
        'tenant_id' => $tenant->id,
        'course_module_id' => $module->id,
        'title' => 'Lesson 1',
        'slug' => 'lesson-1',
        'status' => 'published',
        'sort_order' => 1,
        'is_free' => false,
        'is_active' => true,
    ]);

    $lesson2 = Lesson::query()->create([
        'tenant_id' => $tenant->id,
        'course_module_id' => $module->id,
        'title' => 'Lesson 2',
        'slug' => 'lesson-2',
        'status' => 'published',
        'sort_order' => 2,
        'is_free' => false,
        'is_active' => true,
    ]);

    $historicalEnrollment = Enrollment::query()->create([
        'tenant_id' => $tenant->id,
        'user_id' => $student->id,
        'course_id' => $course->id,
        'status' => 'cancelled',
        'enrolled_at' => now()->subDays(20),
        'progress_percentage' => 15,
    ]);

    LessonProgress::query()->create([
        'tenant_id' => $tenant->id,
        'user_id' => $student->id,
        'course_id' => $course->id,
        'enrollment_id' => $historicalEnrollment->id,
        'lesson_id' => $lesson1->id,
        'is_completed' => true,
        'progress_percentage' => 100,
        'time_spent_seconds' => 300,
        'current_time_seconds' => 300,
        'total_time_seconds' => 300,
        'started_at' => now()->subHours(2),
        'completed_at' => now()->subHour(),
    ]);

    $enrollment = Enrollment::query()->create([
        'tenant_id' => $tenant->id,
        'user_id' => $student->id,
        'course_id' => $course->id,
        'status' => 'active',
        'enrolled_at' => now(),
        'access_expires_at' => now()->addDays(30),
        'progress_percentage' => 20,
    ]);

    LessonProgress::query()->create([
        'tenant_id' => $tenant->id,
        'user_id' => $student->id,
        'course_id' => $course->id,
        'enrollment_id' => $enrollment->id,
        'lesson_id' => $lesson1->id,
        'is_completed' => false,
        'progress_percentage' => 20,
        'time_spent_seconds' => 120,
        'current_time_seconds' => 60,
        'total_time_seconds' => 300,
        'started_at' => now()->subHour(),
        'completed_at' => null,
    ]);

    $token = $student->createToken('test-token')->plainTextToken;

    $this->getJson("/api/v1/learning/courses/{$course->id}/modules", [
        'Authorization' => 'Bearer '.$token,
        'X-Tenant-ID' => (string) $tenant->id,
    ])
        ->assertSuccessful()
        ->assertJsonPath('data.0.id', $module->id)
        ->assertJsonPath('data.0.title', 'Module 1')
        ->assertJsonPath('data.0.lessons.0.id', $lesson1->id)
        ->assertJsonPath('data.0.lessons.0.progress.is_completed', false)
        ->assertJsonPath('data.0.lessons.0.progress.progress_percentage', 20)
        ->assertJsonPath('data.0.lessons.1.id', $lesson2->id)
        ->assertJsonPath('data.0.lessons.1.progress', null);
});

it('returns modules without progress when only historical enrollment exists', function (): void {
    $tenant = Tenant::query()->create([
        'name' => 'Tenant A',
        'domain' => 'tenant-a.local',
        'database' => null,
        'is_active' => true,
    ]);

    $student = User::query()->create([
        'tenant_id' => $tenant->id,
        'name' => 'Student',
        'email' => 'student-no-enroll@tenant-a.test',
        'password' => Hash::make('password123'),
    ]);

    Permission::query()->firstOrCreate(['name' => 'learning.courses.view', 'guard_name' => 'web']);
    Role::query()->firstOrCreate(['name' => 'student', 'guard_name' => 'web'])
        ->givePermissionTo('learning.courses.view');
    $student->assignRole('student');

    $course = Course::query()->create([
        'tenant_id' => $tenant->id,
        'title' => 'Test Course',
        'slug' => 'test-course-no-enroll',
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
        'title' => 'Lesson 1',
        'slug' => 'lesson-1-no-enroll',
        'status' => 'published',
        'sort_order' => 1,
        'is_free' => false,
        'is_active' => true,
    ]);

    $historicalEnrollment = Enrollment::query()->create([
        'tenant_id' => $tenant->id,
        'user_id' => $student->id,
        'course_id' => $course->id,
        'status' => 'expired',
        'enrolled_at' => now()->subDays(40),
        'access_expires_at' => now()->subDay(),
        'progress_percentage' => 80,
    ]);

    LessonProgress::query()->create([
        'tenant_id' => $tenant->id,
        'user_id' => $student->id,
        'course_id' => $course->id,
        'enrollment_id' => $historicalEnrollment->id,
        'lesson_id' => $lesson->id,
        'is_completed' => true,
        'progress_percentage' => 100,
        'time_spent_seconds' => 300,
        'current_time_seconds' => 300,
        'total_time_seconds' => 300,
        'started_at' => now()->subDays(2),
        'completed_at' => now()->subDay(),
    ]);

    $token = $student->createToken('test-token')->plainTextToken;

    $this->getJson("/api/v1/learning/courses/{$course->id}/modules", [
        'Authorization' => 'Bearer '.$token,
        'X-Tenant-ID' => (string) $tenant->id,
    ])
        ->assertSuccessful()
        ->assertJsonPath('data.0.id', $module->id)
        ->assertJsonPath('data.0.lessons.0.progress', null);
});

it('returns 404 for non-existent course', function (): void {
    $tenant = Tenant::query()->create([
        'name' => 'Tenant A',
        'domain' => 'tenant-a.local',
        'database' => null,
        'is_active' => true,
    ]);

    $student = User::query()->create([
        'tenant_id' => $tenant->id,
        'name' => 'Student',
        'email' => 'student-404@tenant-a.test',
        'password' => Hash::make('password123'),
    ]);

    Permission::query()->firstOrCreate(['name' => 'learning.courses.view', 'guard_name' => 'web']);
    Role::query()->firstOrCreate(['name' => 'student', 'guard_name' => 'web'])
        ->givePermissionTo('learning.courses.view');
    $student->assignRole('student');

    $token = $student->createToken('test-token')->plainTextToken;

    $this->getJson('/api/v1/learning/courses/9999/modules', [
        'Authorization' => 'Bearer '.$token,
        'X-Tenant-ID' => (string) $tenant->id,
    ])->assertNotFound();
});

it('requires authentication', function (): void {
    $tenant = Tenant::query()->create([
        'name' => 'Tenant A',
        'domain' => 'tenant-a.local',
        'database' => null,
        'is_active' => true,
    ]);

    $course = Course::query()->create([
        'tenant_id' => $tenant->id,
        'title' => 'Test Course',
        'slug' => 'test-course-auth',
        'description' => 'Course description',
        'status' => 'published',
        'price_cents' => 0,
        'access_days' => 30,
        'is_featured' => false,
    ]);

    $this->getJson("/api/v1/learning/courses/{$course->id}/modules", [
        'X-Tenant-ID' => (string) $tenant->id,
    ])->assertUnauthorized();
});
