<?php

use App\Models\Course;
use App\Models\CourseModule;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

it('shows lesson with can_access true for free lesson', function (): void {
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

    Permission::query()->firstOrCreate(['name' => 'learning.lesson.view', 'guard_name' => 'web']);
    Role::query()->firstOrCreate(['name' => 'student', 'guard_name' => 'web'])
        ->givePermissionTo('learning.lesson.view');
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

    $lesson = Lesson::query()->create([
        'tenant_id' => $tenant->id,
        'course_module_id' => $module->id,
        'title' => 'Free Lesson',
        'slug' => 'free-lesson',
        'status' => 'published',
        'sort_order' => 1,
        'is_free' => true,
    ]);

    $token = $student->createToken('test-token')->plainTextToken;

    $this->getJson("/api/v1/learning/lessons/{$lesson->id}", [
        'Authorization' => 'Bearer '.$token,
        'X-Tenant-ID' => (string) $tenant->id,
    ])
        ->assertSuccessful()
        ->assertJsonPath('data.id', $lesson->id)
        ->assertJsonPath('data.title', 'Free Lesson')
        ->assertJsonPath('data.can_access', true)
        ->assertJsonPath('data.is_free', true);
});

it('denies access to paid lesson without enrollment', function (): void {
    $tenant = Tenant::query()->create([
        'name' => 'Tenant A',
        'domain' => 'tenant-a.local',
        'database' => null,
        'is_active' => true,
    ]);

    $student = User::query()->create([
        'tenant_id' => $tenant->id,
        'name' => 'Student',
        'email' => 'student-paid@tenant-a.test',
        'password' => Hash::make('password123'),
    ]);

    Permission::query()->firstOrCreate(['name' => 'learning.lesson.view', 'guard_name' => 'web']);
    Role::query()->firstOrCreate(['name' => 'student', 'guard_name' => 'web'])
        ->givePermissionTo('learning.lesson.view');
    $student->assignRole('student');

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

    $token = $student->createToken('test-token')->plainTextToken;

    $this->getJson("/api/v1/learning/lessons/{$lesson->id}", [
        'Authorization' => 'Bearer '.$token,
        'X-Tenant-ID' => (string) $tenant->id,
    ])
        ->assertSuccessful()
        ->assertJsonPath('data.can_access', false);
});

it('allows access to paid lesson with active enrollment', function (): void {
    $tenant = Tenant::query()->create([
        'name' => 'Tenant A',
        'domain' => 'tenant-a.local',
        'database' => null,
        'is_active' => true,
    ]);

    $student = User::query()->create([
        'tenant_id' => $tenant->id,
        'name' => 'Student',
        'email' => 'student-enrolled@tenant-a.test',
        'password' => Hash::make('password123'),
    ]);

    Permission::query()->firstOrCreate(['name' => 'learning.lesson.view', 'guard_name' => 'web']);
    Permission::query()->firstOrCreate(['name' => 'learning.lesson.progress', 'guard_name' => 'web']);
    Role::query()->firstOrCreate(['name' => 'student', 'guard_name' => 'web'])
        ->givePermissionTo(['learning.lesson.view', 'learning.lesson.progress']);
    $student->assignRole('student');

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

    $token = $student->createToken('test-token')->plainTextToken;

    $this->getJson("/api/v1/learning/lessons/{$lesson->id}", [
        'Authorization' => 'Bearer '.$token,
        'X-Tenant-ID' => (string) $tenant->id,
    ])
        ->assertSuccessful()
        ->assertJsonPath('data.can_access', true);
});

it('updates lesson progress', function (): void {
    $tenant = Tenant::query()->create([
        'name' => 'Tenant A',
        'domain' => 'tenant-a.local',
        'database' => null,
        'is_active' => true,
    ]);

    $student = User::query()->create([
        'tenant_id' => $tenant->id,
        'name' => 'Student',
        'email' => 'student-progress@tenant-a.test',
        'password' => Hash::make('password123'),
    ]);

    Permission::query()->firstOrCreate(['name' => 'learning.lesson.view', 'guard_name' => 'web']);
    Permission::query()->firstOrCreate(['name' => 'learning.lesson.progress', 'guard_name' => 'web']);
    Role::query()->firstOrCreate(['name' => 'student', 'guard_name' => 'web'])
        ->givePermissionTo(['learning.lesson.view', 'learning.lesson.progress']);
    $student->assignRole('student');

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
    ])
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
    $tenant = Tenant::query()->create([
        'name' => 'Tenant A',
        'domain' => 'tenant-a.local',
        'database' => null,
        'is_active' => true,
    ]);

    $student = User::query()->create([
        'tenant_id' => $tenant->id,
        'name' => 'Student',
        'email' => 'student-complete@tenant-a.test',
        'password' => Hash::make('password123'),
    ]);

    Permission::query()->firstOrCreate(['name' => 'learning.lesson.view', 'guard_name' => 'web']);
    Permission::query()->firstOrCreate(['name' => 'learning.lesson.progress', 'guard_name' => 'web']);
    Role::query()->firstOrCreate(['name' => 'student', 'guard_name' => 'web'])
        ->givePermissionTo(['learning.lesson.view', 'learning.lesson.progress']);
    $student->assignRole('student');

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
    ])
        ->assertSuccessful()
        ->assertJsonPath('data.is_completed', true);

    $enrollment->refresh();
    expect($enrollment->progress_percentage)->toBe(100);
    expect($enrollment->status)->toBe('completed');
});
