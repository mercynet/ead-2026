<?php

use App\Modules\Core\Enums\UserType;
use App\Modules\Core\Models\Tenant;
use App\Modules\Learning\Models\Course;
use App\Modules\Learning\Models\CourseModule;
use App\Modules\Learning\Models\Lesson;
use App\Modules\Learning\Models\Rating;
use App\Modules\Learning\Models\RatingStats;
use Illuminate\Support\Facades\Date;

function makeRatingLessonForTenant(Tenant $tenant, array $courseOverrides = [], array $lessonOverrides = [], array $moduleOverrides = []): Lesson
{
    $course = Course::factory()->create(array_merge([
        'tenant_id' => $tenant->id,
        'status' => 'published',
        'is_active' => true,
        'price_cents' => 0,
    ], $courseOverrides));

    $module = CourseModule::factory()->create(array_merge([
        'tenant_id' => $tenant->id,
        'course_id' => $course->id,
    ], $moduleOverrides));

    return Lesson::factory()->create(array_merge([
        'tenant_id' => $tenant->id,
        'course_module_id' => $module->id,
        'status' => 'published',
        'is_active' => true,
        'is_free' => true,
    ], $lessonOverrides));
}

it('creates a lesson rating and syncs stats', function (): void {
    Date::setTestNow('2026-07-08 10:00:00');

    $tenant = makeTenant(['domain' => 'tenant-a.local']);
    [$student, $headers] = actingAsUserType(UserType::Student, $tenant);
    $lesson = makeRatingLessonForTenant($tenant);

    $response = $this->postJson("/api/v1/learning/lessons/{$lesson->id}/ratings", [
        'stars' => 5,
        'reaction' => 'like',
    ], $headers);

    $response->assertCreated()
        ->assertJsonPath('data.lesson_id', $lesson->id)
        ->assertJsonPath('data.stars', 5)
        ->assertJsonPath('data.reaction', 'like')
        ->assertJsonPath('data.stats.total_ratings', 1)
        ->assertJsonPath('data.stats.five_stars', 1)
        ->assertJsonPath('data.stats.likes_count', 1)
        ->assertJsonPath('data.stats.dislikes_count', 0)
        ->assertJsonPath('data.stats.last_rated_at', now()->toIso8601String())
        ->assertJsonMissingPath('data.rateable_type');

    $this->assertDatabaseHas('ratings', [
        'tenant_id' => $tenant->id,
        'user_id' => $student->id,
        'rateable_type' => $lesson->getMorphClass(),
        'rateable_id' => $lesson->id,
        'stars' => 5,
        'reaction' => 'like',
    ]);

    $stats = RatingStats::query()
        ->where('tenant_id', $tenant->id)
        ->where('rateable_type', $lesson->getMorphClass())
        ->where('rateable_id', $lesson->id)
        ->firstOrFail();

    expect($stats->average_stars)->toBe(5.0)
        ->and($stats->total_ratings)->toBe(1)
        ->and($stats->five_stars)->toBe(1)
        ->and($stats->likes_count)->toBe(1)
        ->and($stats->last_rated_at?->toIso8601String())->toBe(now()->toIso8601String());

    Date::setTestNow();
});

it('updates the existing lesson rating from the same user without duplicating and recalculates stats', function (): void {
    Date::setTestNow('2026-07-08 10:00:00');

    $tenant = makeTenant(['domain' => 'tenant-a.local']);
    [$student, $headers] = actingAsUserType(UserType::Student, $tenant);
    $lesson = makeRatingLessonForTenant($tenant);

    $this->postJson("/api/v1/learning/lessons/{$lesson->id}/ratings", [
        'stars' => 5,
        'reaction' => 'like',
    ], $headers)->assertCreated();

    Date::setTestNow('2026-07-08 11:00:00');

    $response = $this->postJson("/api/v1/learning/lessons/{$lesson->id}/ratings", [
        'stars' => 3,
        'reaction' => 'dislike',
    ], $headers);

    $response->assertOk()
        ->assertJsonPath('data.user_id', $student->id)
        ->assertJsonPath('data.lesson_id', $lesson->id)
        ->assertJsonPath('data.stars', 3)
        ->assertJsonPath('data.reaction', 'dislike')
        ->assertJsonPath('data.stats.total_ratings', 1)
        ->assertJsonPath('data.stats.three_stars', 1)
        ->assertJsonPath('data.stats.likes_count', 0)
        ->assertJsonPath('data.stats.dislikes_count', 1)
        ->assertJsonPath('data.stats.last_rated_at', now()->toIso8601String());

    expect(Rating::query()->where('tenant_id', $tenant->id)->where('rateable_id', $lesson->id)->count())->toBe(1);

    $stats = RatingStats::query()
        ->where('tenant_id', $tenant->id)
        ->where('rateable_type', $lesson->getMorphClass())
        ->where('rateable_id', $lesson->id)
        ->firstOrFail();

    expect($stats->average_stars)->toBe(3.0)
        ->and($stats->total_ratings)->toBe(1)
        ->and($stats->three_stars)->toBe(1)
        ->and($stats->dislikes_count)->toBe(1)
        ->and($stats->last_rated_at?->toIso8601String())->toBe(now()->toIso8601String());

    Date::setTestNow();
});

it('returns 401 without authentication when storing a lesson rating', function (): void {
    $tenant = makeTenant(['domain' => 'tenant-a.local']);
    $lesson = makeRatingLessonForTenant($tenant);

    $this->postJson("/api/v1/learning/lessons/{$lesson->id}/ratings", [
        'stars' => 5,
    ], tenantHeaders($tenant))->assertUnauthorized();
});

it('returns 422 for an invalid lesson rating payload', function (): void {
    $tenant = makeTenant(['domain' => 'tenant-a.local']);
    [, $headers] = actingAsUserType(UserType::Student, $tenant);
    $lesson = makeRatingLessonForTenant($tenant);

    assertApiErrorEnvelope(
        $this->postJson("/api/v1/learning/lessons/{$lesson->id}/ratings", [
            'stars' => 6,
            'reaction' => 'meh',
        ], $headers),
        422,
        'validation_error',
    );
});

it('returns 403 when an admin or instructor rates a lesson', function (): void {
    $tenant = makeTenant(['domain' => 'tenant-a.local']);
    $lesson = makeRatingLessonForTenant($tenant);

    [, $adminHeaders] = actingAsUserType(UserType::Admin, $tenant);
    [, $instructorHeaders] = actingAsUserType(UserType::Instructor, $tenant);

    assertApiErrorEnvelope(
        $this->postJson("/api/v1/learning/lessons/{$lesson->id}/ratings", [
            'stars' => 5,
        ], $adminHeaders),
        403,
        'access_denied',
    );

    assertApiErrorEnvelope(
        $this->postJson("/api/v1/learning/lessons/{$lesson->id}/ratings", [
            'stars' => 5,
        ], $instructorHeaders),
        403,
        'access_denied',
    );
});

it('returns 403 when a student rates a paid lesson without an active enrollment', function (): void {
    $tenant = makeTenant(['domain' => 'tenant-a.local']);
    [, $headers] = actingAsUserType(UserType::Student, $tenant);
    $lesson = makeRatingLessonForTenant($tenant, [
        'price_cents' => 1500,
    ], [
        'is_free' => false,
    ]);

    assertApiErrorEnvelope(
        $this->postJson("/api/v1/learning/lessons/{$lesson->id}/ratings", [
            'stars' => 4,
        ], $headers),
        403,
        'access_denied',
    );
});

it('returns 403 when a student rates a draft or inactive lesson or course', function (): void {
    $tenant = makeTenant(['domain' => 'tenant-a.local']);
    [, $headers] = actingAsUserType(UserType::Student, $tenant);

    $draftLesson = makeRatingLessonForTenant($tenant, [], [
        'status' => 'draft',
        'is_active' => true,
    ]);

    $inactiveLesson = makeRatingLessonForTenant($tenant, [], [
        'status' => 'published',
        'is_active' => false,
    ]);

    $draftCourseLesson = makeRatingLessonForTenant($tenant, [
        'status' => 'draft',
        'is_active' => true,
    ]);

    $inactiveCourseLesson = makeRatingLessonForTenant($tenant, [
        'status' => 'published',
        'is_active' => false,
    ]);

    foreach ([$draftLesson, $inactiveLesson, $draftCourseLesson, $inactiveCourseLesson] as $lesson) {
        assertApiErrorEnvelope(
            $this->postJson("/api/v1/learning/lessons/{$lesson->id}/ratings", [
                'stars' => 4,
            ], $headers),
            403,
            'access_denied',
        );
    }
});

it('returns 404 for a missing or foreign lesson when storing a rating', function (): void {
    $tenantA = makeTenant(['domain' => 'tenant-a.local']);
    $tenantB = makeTenant(['domain' => 'tenant-b.local']);
    [, $headers] = actingAsUserType(UserType::Student, $tenantA);
    $foreignLesson = makeRatingLessonForTenant($tenantB);

    $this->postJson('/api/v1/learning/lessons/999999/ratings', [
        'stars' => 5,
    ], $headers)->assertNotFound();

    $response = $this->postJson("/api/v1/learning/lessons/{$foreignLesson->id}/ratings", [
        'stars' => 5,
    ], $headers);

    assertTenantIsolation($response);
});
