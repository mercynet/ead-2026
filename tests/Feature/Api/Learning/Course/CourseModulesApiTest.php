<?php

use App\Models\Course;
use App\Models\CourseModule;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\LessonProgress;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

it('returns course modules with lesson progress for enrolled user', function (): void {
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

    Permission::query()->firstOrCreate(['name' => 'learning.course.modules', 'guard_name' => 'web']);
    Role::query()->firstOrCreate(['name' => 'student', 'guard_name' => 'web'])
        ->givePermissionTo('learning.course.modules');
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

    $enrollment = Enrollment::query()->create([
        'tenant_id' => $tenant->id,
        'user_id' => $student->id,
        'course_id' => $course->id,
        'status' => 'active',
        'enrolled_at' => now(),
        'access_expires_at' => now()->addDays(30),
        'progress_percentage' => 0,
    ]);

    LessonProgress::query()->create([
        'tenant_id' => $tenant->id,
        'user_id' => $student->id,
        'course_id' => $course->id,
        'enrollment_id' => $enrollment->id,
        'lesson_id' => $lesson1->id,
        'is_completed' => true,
        'progress_percentage' => 100,
        'time_spent_seconds' => 300,
        'current_time_seconds' => 300,
        'total_time_seconds' => 300,
        'started_at' => now()->subHour(),
        'completed_at' => now(),
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
        ->assertJsonPath('data.0.lessons.0.progress.is_completed', true)
        ->assertJsonPath('data.0.lessons.1.id', $lesson2->id)
        ->assertJsonPath('data.0.lessons.1.progress', null);
});

it('returns modules without progress for non-enrolled user', function (): void {
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

    Permission::query()->firstOrCreate(['name' => 'learning.course.modules', 'guard_name' => 'web']);
    Role::query()->firstOrCreate(['name' => 'student', 'guard_name' => 'web'])
        ->givePermissionTo('learning.course.modules');
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

    Lesson::query()->create([
        'tenant_id' => $tenant->id,
        'course_module_id' => $module->id,
        'title' => 'Lesson 1',
        'slug' => 'lesson-1-no-enroll',
        'status' => 'published',
        'sort_order' => 1,
        'is_free' => false,
        'is_active' => true,
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

    Permission::query()->firstOrCreate(['name' => 'learning.course.modules', 'guard_name' => 'web']);
    Role::query()->firstOrCreate(['name' => 'student', 'guard_name' => 'web'])
        ->givePermissionTo('learning.course.modules');
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
