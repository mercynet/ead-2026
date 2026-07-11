<?php

use App\Modules\Core\Enums\UserType;
use App\Modules\Core\Models\Tenant;
use App\Modules\Learning\Models\Course;
use App\Modules\Learning\Models\Rating;
use App\Modules\Learning\Models\RatingStats;
use Illuminate\Support\Facades\Date;

function makeRatingCourseForTenant(Tenant $tenant, array $overrides = []): Course
{
    return Course::factory()->create(array_merge([
        'tenant_id' => $tenant->id,
        'status' => 'published',
        'is_active' => true,
        'price_cents' => 0,
    ], $overrides));
}

it('creates a course rating and syncs stats', function (): void {
    Date::setTestNow('2026-07-08 10:00:00');

    $tenant = makeTenant(['domain' => 'tenant-a.local']);
    [$student, $headers] = actingAsUserType(UserType::Student, $tenant);
    $course = makeRatingCourseForTenant($tenant);

    $response = $this->postJson("/api/v1/learning/courses/{$course->id}/ratings", [
        'stars' => 5,
        'reaction' => 'like',
    ], $headers);

    $response->assertCreated()
        ->assertJsonPath('data.course_id', $course->id)
        ->assertJsonPath('data.stars', 5)
        ->assertJsonPath('data.reaction', 'like')
        ->assertJsonPath('data.stats.total_ratings', 1)
        ->assertJsonPath('data.stats.five_stars', 1)
        ->assertJsonPath('data.stats.likes_count', 1)
        ->assertJsonPath('data.stats.dislikes_count', 0)
        ->assertJsonPath('data.stats.last_rated_at', now()->toIso8601String());

    $this->assertDatabaseHas('ratings', [
        'tenant_id' => $tenant->id,
        'user_id' => $student->id,
        'rateable_type' => $course->getMorphClass(),
        'rateable_id' => $course->id,
        'stars' => 5,
        'reaction' => 'like',
    ]);

    $stats = RatingStats::query()
        ->where('tenant_id', $tenant->id)
        ->where('rateable_type', $course->getMorphClass())
        ->where('rateable_id', $course->id)
        ->firstOrFail();

    expect($stats->average_stars)->toBe(5.0)
        ->and($stats->total_ratings)->toBe(1)
        ->and($stats->five_stars)->toBe(1)
        ->and($stats->likes_count)->toBe(1)
        ->and($stats->last_rated_at?->toIso8601String())->toBe(now()->toIso8601String());

    Date::setTestNow();
});

it('updates the existing rating from the same user without duplicating and recalculates stats', function (): void {
    Date::setTestNow('2026-07-08 10:00:00');

    $tenant = makeTenant(['domain' => 'tenant-a.local']);
    [$student, $headers] = actingAsUserType(UserType::Student, $tenant);
    $course = makeRatingCourseForTenant($tenant);

    $this->postJson("/api/v1/learning/courses/{$course->id}/ratings", [
        'stars' => 5,
        'reaction' => 'like',
    ], $headers)->assertCreated();

    Date::setTestNow('2026-07-08 11:00:00');

    $response = $this->postJson("/api/v1/learning/courses/{$course->id}/ratings", [
        'stars' => 3,
        'reaction' => 'dislike',
    ], $headers);

    $response->assertOk()
        ->assertJsonPath('data.user_id', $student->id)
        ->assertJsonPath('data.course_id', $course->id)
        ->assertJsonPath('data.stars', 3)
        ->assertJsonPath('data.reaction', 'dislike')
        ->assertJsonPath('data.stats.total_ratings', 1)
        ->assertJsonPath('data.stats.three_stars', 1)
        ->assertJsonPath('data.stats.likes_count', 0)
        ->assertJsonPath('data.stats.dislikes_count', 1)
        ->assertJsonPath('data.stats.last_rated_at', now()->toIso8601String());

    expect(Rating::query()->where('tenant_id', $tenant->id)->where('rateable_id', $course->id)->count())->toBe(1);

    $stats = RatingStats::query()
        ->where('tenant_id', $tenant->id)
        ->where('rateable_type', $course->getMorphClass())
        ->where('rateable_id', $course->id)
        ->firstOrFail();

    expect($stats->average_stars)->toBe(3.0)
        ->and($stats->total_ratings)->toBe(1)
        ->and($stats->three_stars)->toBe(1)
        ->and($stats->dislikes_count)->toBe(1)
        ->and($stats->last_rated_at?->toIso8601String())->toBe(now()->toIso8601String());

    Date::setTestNow();
});

it('returns 401 without authentication when storing a course rating', function (): void {
    $tenant = makeTenant(['domain' => 'tenant-a.local']);
    $course = makeRatingCourseForTenant($tenant);

    $this->postJson("/api/v1/learning/courses/{$course->id}/ratings", [
        'stars' => 5,
    ], tenantHeaders($tenant))->assertUnauthorized();
});

it('returns 422 for an invalid course rating payload', function (): void {
    $tenant = makeTenant(['domain' => 'tenant-a.local']);
    [, $headers] = actingAsUserType(UserType::Student, $tenant);
    $course = makeRatingCourseForTenant($tenant);

    assertApiErrorEnvelope(
        $this->postJson("/api/v1/learning/courses/{$course->id}/ratings", [
            'stars' => 6,
            'reaction' => 'meh',
        ], $headers),
        422,
        'validation_error',
    );
});

it('returns 403 when a student without access rates a paid course', function (): void {
    $tenant = makeTenant(['domain' => 'tenant-a.local']);
    [, $headers] = actingAsUserType(UserType::Student, $tenant);
    $course = makeRatingCourseForTenant($tenant, [
        'price_cents' => 1500,
    ]);

    assertApiErrorEnvelope(
        $this->postJson("/api/v1/learning/courses/{$course->id}/ratings", [
            'stars' => 4,
        ], $headers),
        403,
        'access_denied',
    );
});

it('returns 403 when an admin rates a course', function (): void {
    $tenant = makeTenant(['domain' => 'tenant-a.local']);
    [, $headers] = actingAsUserType(UserType::Admin, $tenant);
    $course = makeRatingCourseForTenant($tenant);

    assertApiErrorEnvelope(
        $this->postJson("/api/v1/learning/courses/{$course->id}/ratings", [
            'stars' => 5,
        ], $headers),
        403,
        'access_denied',
    );
});

it('returns 403 when an instructor rates a course', function (): void {
    $tenant = makeTenant(['domain' => 'tenant-a.local']);
    [, $headers] = actingAsUserType(UserType::Instructor, $tenant);
    $course = makeRatingCourseForTenant($tenant);

    assertApiErrorEnvelope(
        $this->postJson("/api/v1/learning/courses/{$course->id}/ratings", [
            'stars' => 5,
        ], $headers),
        403,
        'access_denied',
    );
});

it('returns 403 when a student rates a draft or inactive course', function (): void {
    $tenant = makeTenant(['domain' => 'tenant-a.local']);
    [, $headers] = actingAsUserType(UserType::Student, $tenant);

    $draftCourse = makeRatingCourseForTenant($tenant, [
        'status' => 'draft',
        'is_active' => true,
    ]);

    $inactiveCourse = makeRatingCourseForTenant($tenant, [
        'status' => 'published',
        'is_active' => false,
    ]);

    assertApiErrorEnvelope(
        $this->postJson("/api/v1/learning/courses/{$draftCourse->id}/ratings", [
            'stars' => 4,
        ], $headers),
        403,
        'access_denied',
    );

    assertApiErrorEnvelope(
        $this->postJson("/api/v1/learning/courses/{$inactiveCourse->id}/ratings", [
            'stars' => 4,
        ], $headers),
        403,
        'access_denied',
    );
});

it('returns 404 for a missing or foreign course when storing a rating', function (): void {
    $tenantA = makeTenant(['domain' => 'tenant-a.local']);
    $tenantB = makeTenant(['domain' => 'tenant-b.local']);
    [, $headers] = actingAsUserType(UserType::Admin, $tenantA);
    $foreignCourse = makeRatingCourseForTenant($tenantB);

    $this->postJson('/api/v1/learning/courses/999999/ratings', [
        'stars' => 5,
    ], $headers)->assertNotFound();

    $response = $this->postJson("/api/v1/learning/courses/{$foreignCourse->id}/ratings", [
        'stars' => 5,
    ], $headers);

    assertTenantIsolation($response);
});
