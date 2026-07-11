<?php

use App\Modules\Core\Enums\UserType;
use App\Modules\Core\Models\Tenant;
use App\Modules\Core\Models\User;
use App\Modules\Learning\Models\Course;
use App\Modules\Learning\Models\CourseModule;
use App\Modules\Learning\Models\Lesson;

/**
 * Matriz RBAC (rbac.md): instructor só vê/muta o PRÓPRIO conteúdo (`own`) em
 * courses/modules/lessons; admin segue tenant-wide. Superfícies de consumo
 * (catalog, lesson show/progress) ficam fora — gated por published/enrollment.
 *
 * @return array{tenant: Tenant, owner: User, ownerHeaders: array<string, string>, intruder: User, intruderHeaders: array<string, string>, course: Course, module: CourseModule, lesson: Lesson}
 */
function setupOwnedCourse(): array
{
    $tenant = makeTenant();

    [$owner, $ownerHeaders] = actingAsUserType(UserType::Instructor, $tenant);
    [$intruder, $intruderHeaders] = actingAsUserType(UserType::Instructor, $tenant);

    $course = Course::factory()->for($tenant)->create([
        'instructor_id' => $owner->id,
    ]);

    $module = CourseModule::factory()->for($tenant)->for($course)->create();

    $lesson = Lesson::factory()->for($tenant)->for($module)->create();

    return [
        'tenant' => $tenant,
        'owner' => $owner,
        'ownerHeaders' => $ownerHeaders,
        'intruder' => $intruder,
        'intruderHeaders' => $intruderHeaders,
        'course' => $course,
        'module' => $module,
        'lesson' => $lesson,
    ];
}

// ── Courses ─────────────────────────────────────────────

it('denies instructor updating a course owned by another instructor', function (): void {
    $data = setupOwnedCourse();
    extract($data);

    $response = $this->patchJson("/api/v1/learning/courses/{$course->id}", [
        'title' => 'Tentativa de takeover',
    ], $intruderHeaders);

    assertApiErrorEnvelope($response, 403, 'access_denied');
});

it('allows instructor updating their own course', function (): void {
    $data = setupOwnedCourse();
    extract($data);

    $response = $this->patchJson("/api/v1/learning/courses/{$course->id}", [
        'title' => 'Título atualizado pelo dono',
    ], $ownerHeaders);

    $response->assertSuccessful();

    expect($course->refresh()->title)->toBe('Título atualizado pelo dono');
});

it('allows admin updating any course in the tenant', function (): void {
    $data = setupOwnedCourse();
    extract($data);

    [, $adminHeaders] = actingAsUserType(UserType::Admin, $tenant);

    $this->patchJson("/api/v1/learning/courses/{$course->id}", [
        'title' => 'Admin pode',
    ], $adminHeaders)->assertSuccessful();
});

it('denies instructor deleting a course owned by another instructor', function (): void {
    $data = setupOwnedCourse();
    extract($data);

    $response = $this->deleteJson("/api/v1/learning/courses/{$course->id}", [], $intruderHeaders);

    assertApiErrorEnvelope($response, 403, 'access_denied');

    expect(Course::query()->whereKey($course->id)->exists())->toBeTrue();
});

// ── Modules ─────────────────────────────────────────────

it('denies instructor viewing a module of a course they do not own', function (): void {
    $data = setupOwnedCourse();
    extract($data);

    $response = $this->getJson("/api/v1/learning/modules/{$module->id}", $intruderHeaders);

    assertApiErrorEnvelope($response, 403, 'access_denied');
});

it('denies instructor updating a module of a course they do not own', function (): void {
    $data = setupOwnedCourse();
    extract($data);

    $response = $this->patchJson("/api/v1/learning/modules/{$module->id}", [
        'title' => 'Takeover',
    ], $intruderHeaders);

    assertApiErrorEnvelope($response, 403, 'access_denied');
});

it('denies instructor deleting a module of a course they do not own', function (): void {
    $data = setupOwnedCourse();
    extract($data);

    $response = $this->deleteJson("/api/v1/learning/modules/{$module->id}", [], $intruderHeaders);

    assertApiErrorEnvelope($response, 403, 'access_denied');
});

it('denies instructor creating a module in a course they do not own', function (): void {
    $data = setupOwnedCourse();
    extract($data);

    $response = $this->postJson('/api/v1/learning/modules', [
        'course_id' => $course->id,
        'title' => 'Módulo intruso',
    ], $intruderHeaders);

    assertApiErrorEnvelope($response, 403, 'access_denied');
});

it('allows instructor creating a module in their own course', function (): void {
    $data = setupOwnedCourse();
    extract($data);

    $this->postJson('/api/v1/learning/modules', [
        'course_id' => $course->id,
        'title' => 'Módulo do dono',
    ], $ownerHeaders)->assertCreated();
});

it('denies instructor reordering modules of a course they do not own', function (): void {
    $data = setupOwnedCourse();
    extract($data);

    $response = $this->patchJson('/api/v1/learning/modules/reorder', [
        'course_id' => $course->id,
        'module_ids' => [$module->id],
    ], $intruderHeaders);

    assertApiErrorEnvelope($response, 403, 'access_denied');
});

it('allows admin updating a module of any course in the tenant', function (): void {
    $data = setupOwnedCourse();
    extract($data);

    [, $adminHeaders] = actingAsUserType(UserType::Admin, $tenant);

    $this->patchJson("/api/v1/learning/modules/{$module->id}", [
        'title' => 'Admin pode',
    ], $adminHeaders)->assertSuccessful();
});

// ── Lessons ─────────────────────────────────────────────

it('denies instructor creating a lesson in a module they do not own', function (): void {
    $data = setupOwnedCourse();
    extract($data);

    $response = $this->postJson('/api/v1/learning/lessons', [
        'course_module_id' => $module->id,
        'title' => 'Aula intrusa',
    ], $intruderHeaders);

    assertApiErrorEnvelope($response, 403, 'access_denied');
});

it('allows instructor creating a lesson in their own module', function (): void {
    $data = setupOwnedCourse();
    extract($data);

    $this->postJson('/api/v1/learning/lessons', [
        'course_module_id' => $module->id,
        'title' => 'Aula do dono',
    ], $ownerHeaders)->assertCreated();
});

it('denies instructor updating a lesson of a course they do not own', function (): void {
    $data = setupOwnedCourse();
    extract($data);

    $response = $this->patchJson("/api/v1/learning/lessons/{$lesson->id}", [
        'title' => 'Takeover',
    ], $intruderHeaders);

    assertApiErrorEnvelope($response, 403, 'access_denied');
});

it('allows instructor updating a lesson of their own course', function (): void {
    $data = setupOwnedCourse();
    extract($data);

    $this->patchJson("/api/v1/learning/lessons/{$lesson->id}", [
        'title' => 'Aula atualizada',
    ], $ownerHeaders)->assertSuccessful();
});

it('denies instructor deleting a lesson of a course they do not own', function (): void {
    $data = setupOwnedCourse();
    extract($data);

    $response = $this->deleteJson("/api/v1/learning/lessons/{$lesson->id}", [], $intruderHeaders);

    assertApiErrorEnvelope($response, 403, 'access_denied');

    expect(Lesson::query()->whereKey($lesson->id)->exists())->toBeTrue();
});

it('denies instructor reordering lessons of a module they do not own', function (): void {
    $data = setupOwnedCourse();
    extract($data);

    $response = $this->patchJson('/api/v1/learning/lessons/reorder', [
        'course_module_id' => $module->id,
        'lesson_ids' => [$lesson->id],
    ], $intruderHeaders);

    assertApiErrorEnvelope($response, 403, 'access_denied');
});

it('allows admin deleting a lesson of any course in the tenant', function (): void {
    $data = setupOwnedCourse();
    extract($data);

    [, $adminHeaders] = actingAsUserType(UserType::Admin, $tenant);

    $this->deleteJson("/api/v1/learning/lessons/{$lesson->id}", [], $adminHeaders)->assertSuccessful();
});
