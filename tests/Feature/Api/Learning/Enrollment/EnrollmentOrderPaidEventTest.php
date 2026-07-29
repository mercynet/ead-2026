<?php

use App\Modules\Core\Enums\UserType;
use App\Modules\Core\Models\User;
use App\Modules\Financial\Events\OrderPaidEvent;
use App\Modules\Learning\Models\Course;
use App\Modules\Learning\Models\CourseModule;
use App\Modules\Learning\Models\Enrollment;
use App\Modules\Learning\Models\Lesson;

it('creates an active enrollment when an order paid event carries a course item', function (): void {
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

    Lesson::query()->create([
        'tenant_id' => $tenant->id,
        'course_module_id' => $module->id,
        'title' => 'Lesson 1',
        'slug' => 'lesson-1',
        'status' => 'published',
        'sort_order' => 1,
        'is_free' => false,
    ]);

    event(new OrderPaidEvent(
        orderId: 1,
        tenantId: $tenant->id,
        userId: $student->id,
        paidAt: now()->toIso8601String(),
        items: [[
            'itemable_type' => 'course',
            'itemable_id' => $course->id,
            'item_snapshot' => ['title' => $course->title],
            'price_cents' => 10000,
        ]],
    ));

    expect(Enrollment::query()->where('tenant_id', $tenant->id)->where('user_id', $student->id)->where('course_id', $course->id)->count())->toBe(1);

    $this->getJson("/api/v1/learning/courses/{$course->id}/enrollment", $headers)
        ->assertSuccessful()
        ->assertJsonPath('data.status', 'active')
        ->assertJsonPath('data.is_active', true);
});

it('consumes historical course class payloads, ignores non-learning items, and keeps order paid replays idempotent', function (): void {
    $tenant = makeTenant(['domain' => 'tenant-a.local']);
    [$student, $headers] = actingAsUserType(UserType::Student, $tenant);

    $course = Course::query()->create([
        'tenant_id' => $tenant->id,
        'title' => 'Replay Course',
        'slug' => 'replay-course',
        'description' => 'Course description',
        'status' => 'published',
        'price_cents' => 10000,
        'access_days' => 30,
        'is_featured' => false,
    ]);

    event(new OrderPaidEvent(
        orderId: 99,
        tenantId: $tenant->id,
        userId: $student->id,
        paidAt: now()->toIso8601String(),
        items: [[
            'itemable_type' => User::class,
            'itemable_id' => $student->id,
            'item_snapshot' => ['name' => $student->name],
            'price_cents' => 10000,
        ]],
    ));

    expect(Enrollment::query()->count())->toBe(0);

    $payload = new OrderPaidEvent(
        orderId: 100,
        tenantId: $tenant->id,
        userId: $student->id,
        paidAt: now()->toIso8601String(),
        items: [[
            'itemable_type' => Course::class,
            'itemable_id' => $course->id,
            'item_snapshot' => ['title' => $course->title],
            'price_cents' => 10000,
        ]],
    );

    event($payload);
    event($payload);

    expect(Enrollment::query()->where('tenant_id', $tenant->id)->where('user_id', $student->id)->where('course_id', $course->id)->count())->toBe(1);

    $this->getJson("/api/v1/learning/courses/{$course->id}/enrollment", $headers)
        ->assertSuccessful()
        ->assertJsonPath('data.status', 'active');
});
