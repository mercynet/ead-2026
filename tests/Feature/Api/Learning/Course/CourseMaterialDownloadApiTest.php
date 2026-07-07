<?php

use App\Modules\Core\Enums\UserType;
use App\Modules\Learning\Events\MaterialDownloadedEvent;
use App\Modules\Learning\Models\Course;
use App\Modules\Learning\Models\CourseMaterial;
use App\Modules\Learning\Models\Enrollment;
use App\Modules\Learning\Models\MaterialDownload;
use App\Modules\Learning\Models\MaterialStats;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;

function makeMaterialForCourse(Course $course, ?int $instructorId = null): CourseMaterial
{
    return CourseMaterial::factory()->create([
        'course_id' => $course->id,
        'tenant_id' => $course->tenant_id,
        'instructor_id' => $instructorId,
        'file_path' => 'tenants/'.$course->tenant_id.'/materials/material-'.$course->id.'.pdf',
    ]);
}

it('registers a material download for a student with active enrollment and dispatches an event', function (): void {
    Event::fake([MaterialDownloadedEvent::class]);
    Date::setTestNow('2026-07-07 10:00:00');

    $tenant = makeTenant(['domain' => 'tenant-a.local']);
    [$student, $headers] = actingAsUserType(UserType::Student, $tenant);
    $course = Course::factory()->for($tenant)->create([
        'price_cents' => 1500,
        'status' => 'published',
    ]);
    $material = makeMaterialForCourse($course);

    Enrollment::factory()
        ->for($tenant)
        ->for($student)
        ->for($course)
        ->active()
        ->create();

    Storage::disk(config('filesystems.default'))->put($material->file_path, 'material-body');

    $response = $this->postJson("/api/v1/learning/courses/{$course->id}/materials/{$material->id}/downloads", [], $headers);

    $response->assertCreated()
        ->assertJsonPath('data.course_material_id', $material->id)
        ->assertJsonPath('data.user_id', $student->id)
        ->assertJsonPath('data.download_url_expires_at', now()->addMinutes(5)->toIso8601String());

    expect($response->json('data.download_url'))
        ->toBeString()
        ->not->toBe('');

    $this->assertDatabaseHas('material_downloads', [
        'tenant_id' => $tenant->id,
        'course_material_id' => $material->id,
        'user_id' => $student->id,
    ]);

    Event::assertDispatched(MaterialDownloadedEvent::class, function (MaterialDownloadedEvent $event) use ($course, $material, $student): bool {
        return $event->course->is($course)
            && $event->courseMaterial->is($material)
            && $event->user->is($student)
            && $event->download->course_material_id === $material->id;
    });

    Date::setTestNow();
});

it('updates material stats rollups when a download is registered', function (): void {
    Date::setTestNow('2026-07-15 12:00:00');

    $tenant = makeTenant(['domain' => 'tenant-a.local']);
    [$student, $headers] = actingAsUserType(UserType::Student, $tenant);
    $course = Course::factory()->for($tenant)->create([
        'price_cents' => 1500,
        'status' => 'published',
    ]);
    $material = makeMaterialForCourse($course);

    Enrollment::factory()
        ->for($tenant)
        ->for($student)
        ->for($course)
        ->active()
        ->create();

    MaterialDownload::factory()->for($material, 'courseMaterial')->create([
        'tenant_id' => $tenant->id,
        'user_id' => $student->id,
        'downloaded_at' => now()->copy()->startOfDay()->addHour(),
    ]);
    MaterialDownload::factory()->for($material, 'courseMaterial')->create([
        'tenant_id' => $tenant->id,
        'user_id' => $student->id,
        'downloaded_at' => now()->copy()->subDay(),
    ]);
    MaterialDownload::factory()->for($material, 'courseMaterial')->create([
        'tenant_id' => $tenant->id,
        'user_id' => $student->id,
        'downloaded_at' => now()->copy()->startOfMonth()->addDay(),
    ]);

    $this->postJson("/api/v1/learning/courses/{$course->id}/materials/{$material->id}/downloads", [], $headers)
        ->assertCreated();

    $stats = MaterialStats::query()
        ->where('course_material_id', $material->id)
        ->firstOrFail();

    expect($stats->tenant_id)->toBe($tenant->id)
        ->and($stats->total_downloads)->toBe(4)
        ->and($stats->downloads_today)->toBe(2)
        ->and($stats->downloads_week)->toBe(3)
        ->and($stats->downloads_month)->toBe(4)
        ->and($stats->last_downloaded_at?->toIso8601String())->toBe(now()->toIso8601String());

    Date::setTestNow();
});

it('returns 403 when a student without active enrollment tries to register a paid material download', function (): void {
    $tenant = makeTenant(['domain' => 'tenant-a.local']);
    [, $headers] = actingAsUserType(UserType::Student, $tenant);
    $course = Course::factory()->for($tenant)->create([
        'price_cents' => 1500,
        'status' => 'published',
    ]);
    $material = makeMaterialForCourse($course);

    assertApiErrorEnvelope(
        $this->postJson("/api/v1/learning/courses/{$course->id}/materials/{$material->id}/downloads", [], $headers),
        403,
        'access_denied',
    );
});

it('returns 401 without authentication when registering a material download', function (): void {
    $tenant = makeTenant(['domain' => 'tenant-a.local']);
    $course = Course::factory()->for($tenant)->create([
        'price_cents' => 0,
        'status' => 'published',
    ]);
    $material = makeMaterialForCourse($course);

    $this->postJson("/api/v1/learning/courses/{$course->id}/materials/{$material->id}/downloads", [], tenantHeaders($tenant))
        ->assertUnauthorized();
});

it('returns 404 for a missing or foreign material when registering a download', function (): void {
    $tenantA = makeTenant(['domain' => 'tenant-a.local']);
    $tenantB = makeTenant(['domain' => 'tenant-b.local']);
    [, $headers] = actingAsUserType(UserType::Admin, $tenantA);
    $courseA = Course::factory()->for($tenantA)->create([
        'price_cents' => 0,
        'status' => 'published',
    ]);
    $courseB = Course::factory()->for($tenantB)->create([
        'price_cents' => 0,
        'status' => 'published',
    ]);
    $foreignMaterial = makeMaterialForCourse($courseB);

    $this->postJson("/api/v1/learning/courses/{$courseA->id}/materials/999999/downloads", [], $headers)
        ->assertNotFound();

    $response = $this->postJson("/api/v1/learning/courses/{$courseA->id}/materials/{$foreignMaterial->id}/downloads", [], $headers);

    assertTenantIsolation($response);
});
