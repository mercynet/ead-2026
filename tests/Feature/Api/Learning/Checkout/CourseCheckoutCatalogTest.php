<?php

use App\Modules\Core\Models\Tenant;
use App\Modules\Core\Models\User;
use App\Modules\Learning\Contracts\CourseCheckoutCatalog;
use App\Modules\Learning\Models\Course;
use App\Modules\Learning\Models\Enrollment;
use App\Shared\Exceptions\ResourceNotFoundException;

function checkoutCourseFor(Tenant $tenant, array $attributes = []): Course
{
    return Course::query()->create(array_merge([
        'tenant_id' => $tenant->id,
        'title' => 'Checkout Course',
        'slug' => 'checkout-course-'.fake()->unique()->numberBetween(1, 999999),
        'description' => 'Course description',
        'status' => 'published',
        'is_active' => true,
        'price_cents' => 12500,
        'access_days' => 30,
        'is_featured' => false,
    ], $attributes));
}

function checkoutEnrollment(Course $course, User $user, string $status): Enrollment
{
    return Enrollment::query()->create([
        'tenant_id' => $course->tenant_id,
        'user_id' => $user->id,
        'course_id' => $course->id,
        'status' => $status,
        'progress_percentage' => 0,
    ]);
}

it('resolves authoritative course checkout details and stable morph alias', function (): void {
    $tenant = makeTenant(['domain' => 'checkout.local']);
    $user = User::factory()->student()->forTenant($tenant)->create();
    $course = checkoutCourseFor($tenant, [
        'title' => 'Authoritative title',
        'slug' => 'authoritative-course',
        'price_cents' => 25990,
    ]);

    $offering = app(CourseCheckoutCatalog::class)->resolve($tenant->id, $user->id, $course->id);

    expect($course->getMorphClass())->toBe('course')
        ->and($offering->type)->toBe('course')
        ->and($offering->courseId)->toBe($course->id)
        ->and($offering->priceCents)->toBe(25990)
        ->and($offering->snapshot)->toBe(['title' => 'Authoritative title', 'slug' => 'authoritative-course'])
        ->and($offering->isEligible)->toBeTrue();
});

it('hides missing, cross-tenant, unpublished, inactive, and deleted courses as not found', function (): void {
    $tenant = makeTenant(['domain' => 'checkout.local']);
    $otherTenant = makeTenant(['domain' => 'other-checkout.local']);
    $user = User::factory()->student()->forTenant($tenant)->create();
    $catalog = app(CourseCheckoutCatalog::class);
    $draft = checkoutCourseFor($tenant, ['status' => 'draft']);
    $inactive = checkoutCourseFor($tenant, ['is_active' => false]);
    $deleted = checkoutCourseFor($tenant);
    $deleted->delete();
    $otherTenantCourse = checkoutCourseFor($otherTenant);

    foreach ([999999, $draft->id, $inactive->id, $deleted->id] as $courseId) {
        expect(fn () => $catalog->resolve($tenant->id, $user->id, $courseId))
            ->toThrow(ResourceNotFoundException::class);
    }

    expect(fn () => $catalog->resolve($tenant->id, $user->id, $otherTenantCourse->id))
        ->toThrow(ResourceNotFoundException::class);
});

it('marks active and pending enrollments ineligible', function (string $status): void {
    $tenant = makeTenant(['domain' => 'checkout.local']);
    $user = User::factory()->student()->forTenant($tenant)->create();
    $course = checkoutCourseFor($tenant);
    checkoutEnrollment($course, $user, $status);

    $offering = app(CourseCheckoutCatalog::class)->resolve($tenant->id, $user->id, $course->id);

    expect($offering->isEligible)->toBeFalse()
        ->and($offering->type)->toBe('course');
})->with(['pending', 'active']);

it('allows terminal enrollments with a new stable purchase cycle key', function (string $status): void {
    $tenant = makeTenant(['domain' => 'checkout.local']);
    $user = User::factory()->student()->forTenant($tenant)->create();
    $course = checkoutCourseFor($tenant);
    $catalog = app(CourseCheckoutCatalog::class);
    $initial = $catalog->resolve($tenant->id, $user->id, $course->id);
    checkoutEnrollment($course, $user, $status);

    $reEnrollment = $catalog->resolve($tenant->id, $user->id, $course->id);
    $repeat = $catalog->resolve($tenant->id, $user->id, $course->id);

    expect($reEnrollment->isEligible)->toBeTrue()
        ->and($reEnrollment->purchaseCycleKey)->not->toBe($initial->purchaseCycleKey)
        ->and($repeat->purchaseCycleKey)->toBe($reEnrollment->purchaseCycleKey);
})->with(['cancelled', 'expired']);
