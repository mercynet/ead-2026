<?php

use App\Enums\UserType;
use App\Models\Course;
use App\Models\Enrollment;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('returns null when user has no enrollment for course', function (): void {
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

    $this->getJson("/api/v1/learning/courses/{$course->id}/enrollment", $headers)
        ->assertSuccessful()
        ->assertJsonPath('data', null);
});

it('returns enrollment status for enrolled user', function (): void {
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

    Enrollment::query()->create([
        'tenant_id' => $tenant->id,
        'user_id' => $student->id,
        'course_id' => $course->id,
        'status' => 'active',
        'enrolled_at' => now(),
        'access_expires_at' => now()->addDays(30),
        'progress_percentage' => 45,
    ]);

    $this->getJson("/api/v1/learning/courses/{$course->id}/enrollment", $headers)
        ->assertSuccessful()
        ->assertJsonPath('data.status', 'active')
        ->assertJsonPath('data.is_active', true)
        ->assertJsonPath('data.progress_percentage', 45)
        ->assertJsonPath('data.course.slug', 'test-course');
});

it('returns expired status when enrollment is expired', function (): void {
    $tenant = makeTenant(['domain' => 'tenant-a.local']);
    [$student, $headers] = actingAsUserType(UserType::Student, $tenant);

    $course = Course::query()->create([
        'tenant_id' => $tenant->id,
        'title' => 'Expired Course',
        'slug' => 'expired-course',
        'description' => 'Course description',
        'status' => 'published',
        'price_cents' => 1000,
        'access_days' => 30,
        'is_featured' => false,
    ]);

    Enrollment::query()->create([
        'tenant_id' => $tenant->id,
        'user_id' => $student->id,
        'course_id' => $course->id,
        'status' => 'active',
        'enrolled_at' => now()->subDays(60),
        'access_expires_at' => now()->subDay(),
        'progress_percentage' => 30,
    ]);

    $this->getJson("/api/v1/learning/courses/{$course->id}/enrollment", $headers)
        ->assertSuccessful()
        ->assertJsonPath('data.status', 'active')
        ->assertJsonPath('data.is_active', false);
});

it('requires authentication to view enrollment', function (): void {
    $tenant = makeTenant(['domain' => 'tenant-a.local']);

    $course = Course::query()->create([
        'tenant_id' => $tenant->id,
        'title' => 'Test Course',
        'slug' => 'test-course',
        'description' => 'Course description',
        'status' => 'published',
        'price_cents' => 0,
        'access_days' => 30,
        'is_featured' => false,
    ]);

    $this->getJson("/api/v1/learning/courses/{$course->id}/enrollment", tenantHeaders($tenant))
        ->assertUnauthorized();
});
