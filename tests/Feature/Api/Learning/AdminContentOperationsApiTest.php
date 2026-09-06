<?php

use App\Modules\Core\Enums\UserType;
use App\Modules\Learning\Models\Course;
use App\Modules\Learning\Models\CourseMaterial;
use App\Modules\Learning\Models\CourseModule;
use App\Modules\Learning\Models\Lesson;
use App\Modules\Learning\Models\LessonMedia;

function adminContentCourse(array $attributes = []): Course
{
    return Course::factory()->draft()->create($attributes);
}

it('lets an admin manage tenant content without taking instructor ownership', function (): void {
    $tenant = makeTenant();
    [$admin, $headers] = actingAsUserType(UserType::Admin, $tenant);

    $courseResponse = $this->postJson('/api/v1/admin/courses', [
        'title' => 'Curso administrativo',
        'description' => 'Conteúdo operado pelo tenant admin.',
        'price_cents' => 9900,
        'status' => 'published',
        'tenant_id' => 999999,
        'instructor_id' => 999999,
    ], $headers);

    assertApiErrorEnvelope($courseResponse, 422, 'validation_error');

    $courseResponse = $this->postJson('/api/v1/admin/courses', [
        'title' => 'Curso administrativo',
        'description' => 'Conteúdo operado pelo tenant admin.',
        'price_cents' => 9900,
    ], $headers);

    $courseResponse->assertCreated()
        ->assertJsonPath('data.title', 'Curso administrativo')
        ->assertJsonPath('data.status', 'draft')
        ->assertJsonPath('data.price_cents', 9900);

    $course = Course::query()->where('title', 'Curso administrativo')->firstOrFail();

    expect($course->tenant_id)->toBe($tenant->id)
        ->and($course->instructor_id)->toBeNull();

    $this->getJson('/api/v1/admin/courses', $headers)
        ->assertSuccessful()
        ->assertJsonPath('data.0.id', $course->id)
        ->assertJsonPath('data.0.status', 'draft');

    $this->patchJson('/api/v1/admin/courses/'.$course->id, [
        'title' => 'Curso administrativo atualizado',
        'price_cents' => 12500,
    ], $headers)
        ->assertSuccessful()
        ->assertJsonPath('data.title', 'Curso administrativo atualizado')
        ->assertJsonPath('data.price_cents', 12500);

    $moduleResponse = $this->postJson('/api/v1/admin/modules', [
        'course_id' => $course->id,
        'title' => 'Módulo administrativo',
    ], $headers);

    $moduleResponse->assertCreated()
        ->assertJsonPath('data.course_id', $course->id);

    $module = CourseModule::query()->where('course_id', $course->id)->firstOrFail();

    $secondModuleResponse = $this->postJson('/api/v1/admin/modules', [
        'course_id' => $course->id,
        'title' => 'Segundo módulo administrativo',
    ], $headers);

    $secondModuleResponse->assertCreated();
    $secondModule = CourseModule::query()->where('title', 'Segundo módulo administrativo')->firstOrFail();

    $this->patchJson('/api/v1/admin/modules/reorder', [
        'course_id' => $course->id,
        'module_ids' => [$secondModule->id, $module->id],
    ], $headers)
        ->assertSuccessful();

    expect($secondModule->refresh()->sort_order)->toBe(1)
        ->and($module->refresh()->sort_order)->toBe(2);

    $this->getJson('/api/v1/admin/courses/'.$course->id.'/modules', $headers)
        ->assertSuccessful()
        ->assertJsonPath('data.0.id', $secondModule->id)
        ->assertJsonPath('data.1.id', $module->id);

    $this->patchJson('/api/v1/admin/modules/'.$module->id, [
        'title' => 'Módulo atualizado',
    ], $headers)
        ->assertSuccessful()
        ->assertJsonPath('data.title', 'Módulo atualizado');

    $lessonResponse = $this->postJson('/api/v1/admin/lessons', [
        'course_module_id' => $module->id,
        'title' => 'Aula administrativa',
    ], $headers);

    $lessonResponse->assertCreated()
        ->assertJsonPath('data.title', 'Aula administrativa');

    $lesson = Lesson::query()->where('course_module_id', $module->id)->firstOrFail();

    $secondLessonResponse = $this->postJson('/api/v1/admin/lessons', [
        'course_module_id' => $module->id,
        'title' => 'Segunda aula administrativa',
    ], $headers);

    $secondLessonResponse->assertCreated();
    $secondLesson = Lesson::query()->where('title', 'Segunda aula administrativa')->firstOrFail();

    $this->patchJson('/api/v1/admin/lessons/reorder', [
        'course_module_id' => $module->id,
        'lesson_ids' => [$secondLesson->id, $lesson->id],
    ], $headers)
        ->assertSuccessful();

    expect($secondLesson->refresh()->sort_order)->toBe(1)
        ->and($lesson->refresh()->sort_order)->toBe(2);

    $this->getJson('/api/v1/admin/modules/'.$module->id.'/lessons', $headers)
        ->assertSuccessful()
        ->assertJsonPath('data.0.id', $secondLesson->id)
        ->assertJsonPath('data.1.id', $lesson->id);

    $this->getJson('/api/v1/admin/lessons/'.$lesson->id, $headers)
        ->assertSuccessful()
        ->assertJsonPath('data.id', $lesson->id)
        ->assertJsonPath('data.status', 'draft');

    $this->patchJson('/api/v1/admin/lessons/'.$lesson->id, [
        'title' => 'Aula atualizada',
    ], $headers)
        ->assertSuccessful()
        ->assertJsonPath('data.title', 'Aula atualizada');

    $materialResponse = $this->postJson('/api/v1/admin/courses/'.$course->id.'/materials', [
        'file_path' => 'tenants/'.$tenant->id.'/materials/handout.pdf',
    ], $headers);

    $materialResponse->assertCreated()
        ->assertJsonPath('data.course_id', $course->id)
        ->assertJsonPath('data.instructor_id', null);

    $material = CourseMaterial::query()->where('course_id', $course->id)->firstOrFail();

    $this->getJson('/api/v1/admin/courses/'.$course->id.'/materials', $headers)
        ->assertSuccessful()
        ->assertJsonPath('data.0.id', $material->id);

    $this->patchJson('/api/v1/admin/courses/'.$course->id.'/materials/'.$material->id, [
        'file_path' => 'tenants/'.$tenant->id.'/materials/updated-handout.pdf',
    ], $headers)
        ->assertSuccessful()
        ->assertJsonPath('data.file_path', 'tenants/'.$tenant->id.'/materials/updated-handout.pdf');

    $mediaResponse = $this->postJson('/api/v1/admin/lessons/'.$lesson->id.'/media', [
        'media_type' => 'video',
        'provider' => 'embed',
        'url' => 'https://video.example/admin-lesson',
    ], $headers);

    $mediaResponse->assertCreated()
        ->assertJsonPath('data.lesson_id', $lesson->id);

    $media = LessonMedia::query()->where('lesson_id', $lesson->id)->firstOrFail();

    $this->getJson('/api/v1/admin/lessons/'.$lesson->id.'/media', $headers)
        ->assertSuccessful()
        ->assertJsonPath('data.0.id', $media->id);

    $this->patchJson('/api/v1/admin/lessons/'.$lesson->id.'/media/'.$media->id, [
        'url' => 'https://video.example/admin-lesson-updated',
    ], $headers)
        ->assertSuccessful()
        ->assertJsonPath('data.url', 'https://video.example/admin-lesson-updated');

    $this->deleteJson('/api/v1/admin/lessons/'.$lesson->id.'/media/'.$media->id, [], $headers)
        ->assertSuccessful();

    $this->deleteJson('/api/v1/admin/courses/'.$course->id.'/materials/'.$material->id, [], $headers)
        ->assertSuccessful();

    $this->deleteJson('/api/v1/admin/lessons/'.$lesson->id, [], $headers)
        ->assertSuccessful();

    $this->deleteJson('/api/v1/admin/modules/'.$module->id, [], $headers)
        ->assertSuccessful();

    $this->deleteJson('/api/v1/admin/courses/'.$course->id, [], $headers)
        ->assertSuccessful();

    expect(Course::query()->find($course->id))->toBeNull()
        ->and(CourseMaterial::query()->find($material->id))->toBeNull()
        ->and(LessonMedia::query()->find($media->id))->toBeNull();
});

it('keeps the admin content surface exclusive to the admin persona', function (): void {
    $tenant = makeTenant();

    foreach ([UserType::Instructor, UserType::Student, UserType::Developer] as $type) {
        [, $headers] = actingAsUserType($type, $type === UserType::Developer ? null : $tenant);

        assertApiErrorEnvelope(
            $this->getJson('/api/v1/admin/courses/999999', $headers),
            403,
            'area_forbidden'
        );
    }
});

it('keeps instructor ownership on the legacy authoring surface', function (): void {
    $tenant = makeTenant();
    [$instructor, $instructorHeaders] = actingAsUserType(UserType::Instructor, $tenant);
    $course = adminContentCourse([
        'tenant_id' => $tenant->id,
        'instructor_id' => $instructor->id,
    ]);

    assertApiErrorEnvelope(
        $this->getJson('/api/v1/admin/courses/'.$course->id, $instructorHeaders),
        403,
        'area_forbidden'
    );

    $this->patchJson('/api/v1/learning/courses/'.$course->id, [
        'title' => 'Instructor atualiza seu curso',
    ], $instructorHeaders)->assertSuccessful();

    expect($course->refresh()->instructor_id)->toBe($instructor->id)
        ->and($course->title)->toBe('Instructor atualiza seu curso');
});

it('isolates admin content by tenant and rejects scope spoofing', function (): void {
    $tenantA = makeTenant();
    $tenantB = makeTenant();
    [, $headersA] = actingAsUserType(UserType::Admin, $tenantA);
    $courseB = adminContentCourse(['tenant_id' => $tenantB->id]);

    assertApiErrorEnvelope(
        $this->getJson('/api/v1/admin/courses/'.$courseB->id, $headersA),
        404,
        'not_found'
    );

    assertApiErrorEnvelope(
        $this->postJson('/api/v1/admin/modules', [
            'course_id' => $courseB->id,
            'title' => 'Módulo cross-tenant',
        ], $headersA),
        422,
        'validation_error'
    );

    expect(CourseModule::query()->where('title', 'Módulo cross-tenant')->exists())->toBeFalse();
});

it('rejects invalid module reorder without changing tenant content', function (): void {
    $tenant = makeTenant();
    [, $headers] = actingAsUserType(UserType::Admin, $tenant);
    $course = adminContentCourse(['tenant_id' => $tenant->id]);
    $module = CourseModule::factory()->for($tenant)->for($course)->create(['sort_order' => 1]);

    assertApiErrorEnvelope(
        $this->patchJson('/api/v1/admin/modules/reorder', [
            'course_id' => $course->id,
            'module_ids' => [],
        ], $headers),
        422,
        'validation_error'
    );

    expect($module->refresh()->sort_order)->toBe(1);
});

it('requires authentication for the admin content surface', function (): void {
    $tenant = makeTenant();

    assertApiErrorEnvelope(
        $this->getJson('/api/v1/admin/courses', tenantHeaders($tenant)),
        401,
        'unauthenticated'
    );
});
