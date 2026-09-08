<?php

use App\Modules\Core\Enums\UserType;
use App\Modules\Core\Models\User;
use App\Modules\Learning\Models\Course;
use App\Modules\Learning\Models\CourseModule;
use App\Modules\Learning\Models\Enrollment;
use App\Modules\Learning\Models\Lesson;
use Illuminate\Support\Facades\DB;

it('keeps Student lesson navigation query count bounded as lessons grow', function (): void {
    $tenant = makeTenant();
    [$student, $headers] = actingAsUserType(UserType::Student, $tenant);
    $course = Course::factory()->for($tenant)->create([
        'status' => 'published',
        'is_active' => true,
    ]);
    $module = CourseModule::factory()->for($tenant)->for($course)->create();
    Lesson::factory()->for($tenant)->for($module)->create([
        'status' => 'published',
        'is_active' => true,
    ]);
    Enrollment::factory()->active()->for($tenant)->for($course)->for($student, 'user')->create();

    $measure = function () use ($course, $module, $headers): int {
        DB::flushQueryLog();
        DB::enableQueryLog();
        test()->getJson("/api/v1/student/courses/{$course->id}/modules/{$module->id}/lessons", $headers)->assertSuccessful();
        $queryCount = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $queryCount;
    };

    $smallQueryCount = $measure();
    Lesson::factory()->count(5)->for($tenant)->for($module)->create([
        'status' => 'published',
        'is_active' => true,
    ]);
    $largeQueryCount = $measure();

    expect($largeQueryCount)->toBeLessThanOrEqual($smallQueryCount + 2);
});

it('keeps Instructor roster query count bounded as enrollments grow', function (): void {
    $tenant = makeTenant();
    [$instructor, $headers] = actingAsUserType(UserType::Instructor, $tenant);
    $course = Course::factory()->for($tenant)->create([
        'instructor_id' => $instructor->id,
        'status' => 'published',
        'is_active' => true,
    ]);
    $module = CourseModule::factory()->for($tenant)->for($course)->create();
    Lesson::factory()->for($tenant)->for($module)->create([
        'status' => 'published',
        'is_active' => true,
    ]);

    $createEnrollment = function () use ($tenant, $course): void {
        $student = User::factory()->student()->forTenant($tenant)->create();
        Enrollment::factory()->active()->for($tenant)->for($course)->for($student, 'user')->create();
    };
    $createEnrollment();

    $measure = function () use ($headers): int {
        DB::flushQueryLog();
        DB::enableQueryLog();
        test()->getJson('/api/v1/instructor/enrollments', $headers)->assertSuccessful();
        $queryCount = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $queryCount;
    };

    $smallQueryCount = $measure();
    foreach (range(1, 5) as $ignored) {
        $createEnrollment();
    }
    $largeQueryCount = $measure();

    expect($largeQueryCount)->toBeLessThanOrEqual($smallQueryCount + 2);
});
