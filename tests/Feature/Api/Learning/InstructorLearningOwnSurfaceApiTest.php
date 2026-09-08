<?php

use App\Modules\Core\Enums\UserType;
use App\Modules\Learning\Models\Course;
use App\Modules\Learning\Models\CourseModule;
use App\Modules\Learning\Models\Lesson;

it('lists only the authenticated instructor own courses, including drafts and inactive courses', function (): void {
    $tenant = makeTenant();
    $otherTenant = makeTenant();

    [$instructor, $headers] = actingAsUserType(UserType::Instructor, $tenant);
    [$otherInstructor] = actingAsUserType(UserType::Instructor, $tenant);

    $ownDraft = Course::factory()->draft()->for($tenant)->create([
        'instructor_id' => $instructor->id,
        'is_active' => false,
        'title' => 'Meu draft inativo',
    ]);
    $otherOwnerCourse = Course::factory()->for($tenant)->create([
        'instructor_id' => $otherInstructor->id,
        'title' => 'Curso de outro instructor',
    ]);
    $tenantOwnedCourse = Course::factory()->for($tenant)->create([
        'instructor_id' => null,
        'title' => 'Curso do tenant',
    ]);
    $foreignCourse = Course::factory()->for($otherTenant)->create([
        'instructor_id' => $instructor->id,
        'title' => 'Curso de outro tenant',
    ]);

    $response = $this->getJson('/api/v1/instructor/courses', $headers);

    $response->assertSuccessful()
        ->assertJsonStructure(['data' => [['id', 'title', 'status', 'instructor_id']]])
        ->assertJsonPath('data.0.id', $ownDraft->id);

    expect($response->json('data'))->toHaveCount(1)
        ->and($response->json('data.*.id'))->toBe([$ownDraft->id])
        ->and($response->json('data.*.id'))->not->toContain($otherOwnerCourse->id)
        ->and($response->json('data.*.id'))->not->toContain($tenantOwnedCourse->id)
        ->and($response->json('data.*.id'))->not->toContain($foreignCourse->id);
});

it('shows only an own course and returns a defensive 404 for another owner, null owner, or tenant', function (): void {
    $tenant = makeTenant();
    $otherTenant = makeTenant();

    [$instructor, $headers] = actingAsUserType(UserType::Instructor, $tenant);
    [$otherInstructor] = actingAsUserType(UserType::Instructor, $tenant);

    $ownCourse = Course::factory()->draft()->for($tenant)->create([
        'instructor_id' => $instructor->id,
        'is_active' => false,
    ]);
    $otherOwnerCourse = Course::factory()->for($tenant)->create([
        'instructor_id' => $otherInstructor->id,
    ]);
    $tenantOwnedCourse = Course::factory()->for($tenant)->create([
        'instructor_id' => null,
    ]);
    $foreignCourse = Course::factory()->for($otherTenant)->create([
        'instructor_id' => $instructor->id,
    ]);

    $this->getJson("/api/v1/instructor/courses/{$ownCourse->id}", $headers)
        ->assertSuccessful()
        ->assertJsonPath('data.id', $ownCourse->id)
        ->assertJsonPath('data.status', 'draft')
        ->assertJsonPath('data.is_active', false);

    foreach ([$otherOwnerCourse, $tenantOwnedCourse, $foreignCourse] as $course) {
        assertApiErrorEnvelope(
            $this->getJson("/api/v1/instructor/courses/{$course->id}", $headers),
            404,
            'not_found'
        );
    }
});

it('requires authentication and the instructor area', function (): void {
    assertApiErrorEnvelope($this->getJson('/api/v1/instructor/courses'), 401, 'unauthenticated');

    [, $adminHeaders] = actingAsUserType(UserType::Admin, makeTenant());

    assertApiErrorEnvelope($this->getJson('/api/v1/instructor/courses', $adminHeaders), 403, 'area_forbidden');
});

it('requires the canonical Course list permission inside the Instructor area', function (): void {
    $tenant = makeTenant();
    [$instructor, $headers] = actingAsUserType(UserType::Instructor, $tenant);
    $instructor->removeRole('instructor');

    assertApiErrorEnvelope($this->getJson('/api/v1/instructor/courses', $headers), 403, 'access_denied');
});

it('creates an own draft course with tenant and instructor derived from context', function (): void {
    $tenant = makeTenant();
    [$instructor, $headers] = actingAsUserType(UserType::Instructor, $tenant);

    $response = $this->postJson('/api/v1/instructor/courses', [
        'title' => 'Curso próprio',
        'tenant_id' => makeTenant()->id,
        'instructor_id' => 999999,
        'owner' => 'admin',
        'status' => 'published',
        'price_cents' => 2500,
    ], $headers);

    assertApiErrorEnvelope($response, 422, 'validation_error');
    expect(Course::query()->where('title', 'Curso próprio')->exists())->toBeFalse();

    $response = $this->postJson('/api/v1/instructor/courses', [
        'title' => 'Curso próprio',
        'price_cents' => 2500,
    ], $headers);

    $response->assertCreated()->assertJsonPath('data.status', 'draft');

    $course = Course::query()->where('title', 'Curso próprio')->firstOrFail();

    expect($course->tenant_id)->toBe($tenant->id)
        ->and($course->instructor_id)->toBe($instructor->id)
        ->and($course->status)->toBe('draft');
});

it('updates own course metadata without accepting lifecycle or ownership changes', function (): void {
    $tenant = makeTenant();
    [$instructor, $headers] = actingAsUserType(UserType::Instructor, $tenant);
    $course = Course::factory()->draft()->for($tenant)->create([
        'instructor_id' => $instructor->id,
    ]);

    assertApiErrorEnvelope($this->patchJson("/api/v1/instructor/courses/{$course->id}", [
        'title' => 'Tentativa de publicar',
        'status' => 'published',
        'instructor_id' => 999999,
        'tenant_id' => makeTenant()->id,
    ], $headers), 422, 'validation_error');

    $this->patchJson("/api/v1/instructor/courses/{$course->id}", [
        'title' => 'Título próprio atualizado',
        'price_cents' => 3500,
    ], $headers)->assertSuccessful();

    expect($course->refresh()->title)->toBe('Título próprio atualizado')
        ->and($course->status)->toBe('draft')
        ->and($course->instructor_id)->toBe($instructor->id);
});

it('supports own module CRUD and transitively hides another instructor module', function (): void {
    $tenant = makeTenant();
    [$instructor, $headers] = actingAsUserType(UserType::Instructor, $tenant);
    [$otherInstructor] = actingAsUserType(UserType::Instructor, $tenant);
    $course = Course::factory()->draft()->for($tenant)->create(['instructor_id' => $instructor->id]);
    $otherCourse = Course::factory()->for($tenant)->create(['instructor_id' => $otherInstructor->id]);
    $otherModule = CourseModule::factory()->for($tenant)->for($otherCourse)->create();

    $create = $this->postJson('/api/v1/instructor/modules', [
        'course_id' => $course->id,
        'title' => 'Meu módulo',
    ], $headers)->assertCreated();

    $module = CourseModule::query()->where('title', 'Meu módulo')->firstOrFail();

    $create->assertJsonPath('data.course_id', $course->id);

    $this->getJson("/api/v1/instructor/courses/{$course->id}/modules", $headers)
        ->assertSuccessful()
        ->assertJsonPath('data.0.id', $module->id);

    assertApiErrorEnvelope($this->getJson("/api/v1/instructor/modules/{$otherModule->id}", $headers), 404, 'not_found');
    assertApiErrorEnvelope($this->patchJson("/api/v1/instructor/modules/{$otherModule->id}", [
        'title' => 'Takeover',
    ], $headers), 404, 'not_found');

    $this->patchJson("/api/v1/instructor/modules/{$module->id}", [
        'title' => 'Meu módulo atualizado',
    ], $headers)->assertSuccessful();

    expect($module->refresh()->title)->toBe('Meu módulo atualizado');

    $this->deleteJson("/api/v1/instructor/modules/{$module->id}", [], $headers)
        ->assertSuccessful();

    expect(CourseModule::query()->whereKey($module->id)->exists())->toBeFalse();
});

it('rejects module parent spoofing, foreign reorder members, and incomplete reorders', function (): void {
    $tenant = makeTenant();
    [$instructor, $headers] = actingAsUserType(UserType::Instructor, $tenant);
    [$otherInstructor] = actingAsUserType(UserType::Instructor, $tenant);
    $course = Course::factory()->for($tenant)->create(['instructor_id' => $instructor->id]);
    $otherCourse = Course::factory()->for($tenant)->create(['instructor_id' => $otherInstructor->id]);
    $module = CourseModule::factory()->for($tenant)->for($course)->create(['sort_order' => 1]);
    $otherModule = CourseModule::factory()->for($tenant)->for($otherCourse)->create(['sort_order' => 1]);

    assertApiErrorEnvelope($this->postJson('/api/v1/instructor/modules', [
        'course_id' => $otherCourse->id,
        'title' => 'Parent spoof',
    ], $headers), 403, 'access_denied');

    assertApiErrorEnvelope($this->patchJson('/api/v1/instructor/modules/reorder', [
        'course_id' => $course->id,
        'module_ids' => [$module->id, $otherModule->id],
    ], $headers), 422, 'validation_error');

    assertApiErrorEnvelope($this->patchJson('/api/v1/instructor/modules/reorder', [
        'course_id' => $course->id,
        'module_ids' => [],
    ], $headers), 422, 'validation_error');
});

it('rejects modules and lessons from another tenant even when the actor id is reused in fixture data', function (): void {
    $tenant = makeTenant();
    $otherTenant = makeTenant();
    [$instructor, $headers] = actingAsUserType(UserType::Instructor, $tenant);
    $foreignCourse = Course::factory()->for($otherTenant)->create(['instructor_id' => $instructor->id]);
    $foreignModule = CourseModule::factory()->for($otherTenant)->for($foreignCourse)->create();
    $foreignLesson = Lesson::factory()->for($otherTenant)->for($foreignModule)->create();

    assertApiErrorEnvelope($this->getJson("/api/v1/instructor/modules/{$foreignModule->id}", $headers), 404, 'not_found');
    assertApiErrorEnvelope($this->getJson("/api/v1/instructor/lessons/{$foreignLesson->id}", $headers), 404, 'not_found');

    assertApiErrorEnvelope($this->postJson('/api/v1/instructor/modules', [
        'course_id' => $foreignCourse->id,
        'title' => 'Cross tenant parent',
    ], $headers), 422, 'validation_error');

    assertApiErrorEnvelope($this->postJson('/api/v1/instructor/lessons', [
        'course_module_id' => $foreignModule->id,
        'title' => 'Cross tenant parent',
    ], $headers), 422, 'validation_error');
});

it('supports own lesson CRUD and transitively rejects a foreign module parent', function (): void {
    $tenant = makeTenant();
    [$instructor, $headers] = actingAsUserType(UserType::Instructor, $tenant);
    [$otherInstructor] = actingAsUserType(UserType::Instructor, $tenant);
    $course = Course::factory()->for($tenant)->create(['instructor_id' => $instructor->id]);
    $otherCourse = Course::factory()->for($tenant)->create(['instructor_id' => $otherInstructor->id]);
    $module = CourseModule::factory()->for($tenant)->for($course)->create();
    $otherModule = CourseModule::factory()->for($tenant)->for($otherCourse)->create();

    $response = $this->postJson('/api/v1/instructor/lessons', [
        'course_module_id' => $module->id,
        'title' => 'Minha aula',
        'status' => 'published',
    ], $headers);

    assertApiErrorEnvelope($response, 422, 'validation_error');

    $lesson = $this->postJson('/api/v1/instructor/lessons', [
        'course_module_id' => $module->id,
        'title' => 'Minha aula',
    ], $headers)->assertCreated();
    $lessonModel = Lesson::query()->where('title', 'Minha aula')->firstOrFail();

    $lesson->assertJsonPath('data.course.id', $course->id);

    $this->getJson("/api/v1/instructor/modules/{$module->id}/lessons", $headers)
        ->assertSuccessful()
        ->assertJsonPath('data.0.id', $lessonModel->id);

    assertApiErrorEnvelope($this->postJson('/api/v1/instructor/lessons', [
        'course_module_id' => $otherModule->id,
        'title' => 'Aula intrusa',
    ], $headers), 403, 'access_denied');

    $this->patchJson("/api/v1/instructor/lessons/{$lessonModel->id}", [
        'title' => 'Minha aula atualizada',
    ], $headers)->assertSuccessful();

    expect($lessonModel->refresh()->title)->toBe('Minha aula atualizada')
        ->and($lessonModel->status)->toBe('draft');

    $this->deleteJson("/api/v1/instructor/lessons/{$lessonModel->id}", [], $headers)
        ->assertSuccessful();

    expect(Lesson::query()->whereKey($lessonModel->id)->exists())->toBeFalse();
});

it('rejects lesson reorder across parents and lifecycle spoofing', function (): void {
    $tenant = makeTenant();
    [$instructor, $headers] = actingAsUserType(UserType::Instructor, $tenant);
    [$otherInstructor] = actingAsUserType(UserType::Instructor, $tenant);
    $course = Course::factory()->for($tenant)->create(['instructor_id' => $instructor->id]);
    $otherCourse = Course::factory()->for($tenant)->create(['instructor_id' => $otherInstructor->id]);
    $module = CourseModule::factory()->for($tenant)->for($course)->create();
    $otherModule = CourseModule::factory()->for($tenant)->for($otherCourse)->create();
    $lesson = Lesson::factory()->for($tenant)->for($module)->create(['sort_order' => 1]);
    $otherLesson = Lesson::factory()->for($tenant)->for($otherModule)->create(['sort_order' => 1]);

    assertApiErrorEnvelope($this->patchJson("/api/v1/instructor/lessons/{$lesson->id}", [
        'title' => 'Lifecycle spoof',
        'status' => 'published',
    ], $headers), 422, 'validation_error');

    assertApiErrorEnvelope($this->patchJson('/api/v1/instructor/lessons/reorder', [
        'course_module_id' => $module->id,
        'lesson_ids' => [$lesson->id, $otherLesson->id],
    ], $headers), 422, 'validation_error');

    assertApiErrorEnvelope($this->getJson("/api/v1/instructor/lessons/{$otherLesson->id}", $headers), 404, 'not_found');
});

it('can read System and Custom categories through the existing catalog read surface only', function (): void {
    $tenant = makeTenant();
    [, $headers] = actingAsUserType(UserType::Instructor, $tenant);

    $this->getJson('/api/v1/learning/catalog/categories', $headers)->assertSuccessful();

    assertApiErrorEnvelope($this->postJson('/api/v1/instructor/categories', [
        'name' => 'Taxonomia proibida',
    ], $headers), 404, 'not_found');
});
