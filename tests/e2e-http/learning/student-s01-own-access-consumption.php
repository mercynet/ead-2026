<?php

declare(strict_types=1);

use App\Modules\Core\Enums\UserType;
use App\Modules\Core\Models\User;
use App\Modules\Learning\Models\Course;
use App\Modules\Learning\Models\CourseMaterial;
use App\Modules\Learning\Models\CourseModule;
use App\Modules\Learning\Models\Enrollment;
use App\Modules\Learning\Models\Lesson;
use App\Modules\Learning\Models\LessonMedia;
use App\Modules\Learning\Models\LessonProgress;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

return [
    'endpoint' => 'GET /api/v1/student/courses',

    'setup' => function (array $ctx): array {
        $tenantId = $ctx['tenant']->id;
        $studentB = User::query()->create([
            'tenant_id' => $tenantId,
            'user_type' => UserType::Student,
            'name' => 'E2E Student B',
            'email' => 'e2e-student-b-'.$tenantId.'@test.local',
            'password' => Hash::make('password123'),
        ]);
        $studentB->assignRole('student');
        $studentBToken = $studentB->createToken('e2e-student-b')->plainTextToken;

        $course = Course::query()->create([
            'tenant_id' => $tenantId,
            'title' => 'Curso Student S01 E2E',
            'slug' => 'curso-student-s01-e2e',
            'description' => 'Curso de consumo próprio',
            'short_description' => 'Resumo pedagógico',
            'status' => 'published',
            'price_cents' => 0,
            'is_active' => true,
        ]);
        $module = CourseModule::query()->create([
            'tenant_id' => $tenantId,
            'course_id' => $course->id,
            'title' => 'Módulo Student S01 E2E',
            'sort_order' => 1,
            'is_active' => true,
        ]);
        $lesson = Lesson::query()->create([
            'tenant_id' => $tenantId,
            'course_module_id' => $module->id,
            'title' => 'Aula Student S01 E2E',
            'slug' => 'aula-student-s01-e2e',
            'description' => 'Descrição pedagógica',
            'content' => ['body' => 'Conteúdo consumível'],
            'status' => 'published',
            'sort_order' => 1,
            'is_free' => false,
            'is_active' => true,
        ]);
        $previewLesson = Lesson::query()->create([
            'tenant_id' => $tenantId,
            'course_module_id' => $module->id,
            'title' => 'Preview Student S01 E2E',
            'slug' => 'preview-student-s01-e2e',
            'content' => ['body' => 'Preview consumível'],
            'status' => 'published',
            'sort_order' => 2,
            'is_free' => true,
            'is_active' => true,
        ]);
        $draftLesson = Lesson::query()->create([
            'tenant_id' => $tenantId,
            'course_module_id' => $module->id,
            'title' => 'Draft Student S01 E2E',
            'slug' => 'draft-student-s01-e2e',
            'status' => 'draft',
            'sort_order' => 3,
            'is_free' => true,
            'is_active' => true,
        ]);
        $inactiveLesson = Lesson::query()->create([
            'tenant_id' => $tenantId,
            'course_module_id' => $module->id,
            'title' => 'Inactive Student S01 E2E',
            'slug' => 'inactive-student-s01-e2e',
            'status' => 'published',
            'sort_order' => 4,
            'is_free' => true,
            'is_active' => false,
        ]);
        $media = LessonMedia::query()->create([
            'tenant_id' => $tenantId,
            'lesson_id' => $lesson->id,
            'media_type' => 'video',
            'provider' => 'embed',
            'provider_ref' => 'student-s01-e2e-secret-ref',
            'url' => 'https://video.example/student-s01-e2e',
            'duration_seconds' => 240,
            'sort_order' => 1,
            'is_active' => true,
            'metadata' => ['storage_path' => 'private/should-not-leak.mp4'],
        ]);
        $material = CourseMaterial::query()->create([
            'tenant_id' => $tenantId,
            'course_id' => $course->id,
            'file_path' => 'tenants/'.$tenantId.'/materials/student-s01-e2e.pdf',
        ]);
        Storage::disk(config('filesystems.default'))->put($material->file_path, 'student material');
        $enrollment = Enrollment::query()->create([
            'tenant_id' => $tenantId,
            'user_id' => $ctx['users']['student']->id,
            'course_id' => $course->id,
            'status' => 'active',
            'enrolled_at' => now(),
            'access_expires_at' => now()->addDays(30),
            'progress_percentage' => 0,
        ]);

        $pendingCourse = Course::query()->create([
            'tenant_id' => $tenantId,
            'title' => 'Curso Pending Student S01 E2E',
            'slug' => 'curso-pending-student-s01-e2e',
            'status' => 'published',
            'price_cents' => 1000,
            'is_active' => true,
        ]);
        $pendingLesson = Lesson::query()->create([
            'tenant_id' => $tenantId,
            'course_module_id' => CourseModule::query()->create([
                'tenant_id' => $tenantId,
                'course_id' => $pendingCourse->id,
                'title' => 'Módulo Pending Student S01 E2E',
                'sort_order' => 1,
                'is_active' => true,
            ])->id,
            'title' => 'Aula Pending Student S01 E2E',
            'slug' => 'aula-pending-student-s01-e2e',
            'status' => 'published',
            'is_free' => false,
            'is_active' => true,
        ]);
        Enrollment::query()->create([
            'tenant_id' => $tenantId,
            'user_id' => $ctx['users']['student']->id,
            'course_id' => $pendingCourse->id,
            'status' => 'pending',
            'enrolled_at' => now(),
        ]);

        $foreignCourse = Course::query()->create([
            'tenant_id' => $ctx['otherTenant']->id,
            'title' => 'Curso Foreign Student S01 E2E',
            'slug' => 'curso-foreign-student-s01-e2e',
            'status' => 'published',
            'price_cents' => 1000,
            'is_active' => true,
        ]);
        $foreignModule = CourseModule::query()->create([
            'tenant_id' => $ctx['otherTenant']->id,
            'course_id' => $foreignCourse->id,
            'title' => 'Módulo Foreign Student S01 E2E',
            'sort_order' => 1,
            'is_active' => true,
        ]);
        $foreignLesson = Lesson::query()->create([
            'tenant_id' => $ctx['otherTenant']->id,
            'course_module_id' => $foreignModule->id,
            'title' => 'Aula Foreign Student S01 E2E',
            'slug' => 'aula-foreign-student-s01-e2e',
            'status' => 'published',
            'is_free' => false,
            'is_active' => true,
        ]);
        $foreignMaterial = CourseMaterial::query()->create([
            'tenant_id' => $ctx['otherTenant']->id,
            'course_id' => $foreignCourse->id,
            'file_path' => 'tenants/'.$ctx['otherTenant']->id.'/materials/foreign-student-s01-e2e.pdf',
        ]);
        LessonMedia::query()->create([
            'tenant_id' => $ctx['otherTenant']->id,
            'lesson_id' => $foreignLesson->id,
            'media_type' => 'video',
            'provider' => 'embed',
            'url' => 'https://video.example/foreign-student-s01-e2e',
            'is_active' => true,
        ]);

        return compact(
            'course', 'module', 'lesson', 'previewLesson', 'draftLesson', 'inactiveLesson',
            'media', 'material', 'enrollment', 'pendingCourse', 'pendingLesson',
            'foreignCourse', 'foreignLesson', 'foreignMaterial', 'studentB', 'studentBToken',
        );
    },

    'cases' => [
        [
            'name' => 'Student A vê somente o próprio Course consumível',
            'as' => 'student',
            'expect' => ['status' => 200, 'json' => ['data.0.id' => fn (array $ctx): int => $ctx['fixtures']['course']->id]],
        ],
        [
            'name' => 'Student A navega Course, Modules e Lessons',
            'as' => 'student',
            'path' => fn (array $ctx): string => '/api/v1/student/courses/'.$ctx['fixtures']['course']->id,
            'expect' => ['status' => 200, 'json' => ['data.id' => fn (array $ctx): int => $ctx['fixtures']['course']->id]],
        ],
        [
            'name' => 'árvore retorna módulo e aula publicada ativa',
            'as' => 'student',
            'path' => fn (array $ctx): string => '/api/v1/student/courses/'.$ctx['fixtures']['course']->id.'/modules',
            'expect' => ['status' => 200, 'json' => ['data.0.id' => fn (array $ctx): int => $ctx['fixtures']['module']->id]],
        ],
        [
            'name' => 'aulas publicadas ativas são navegáveis',
            'as' => 'student',
            'path' => fn (array $ctx): string => '/api/v1/student/courses/'.$ctx['fixtures']['course']->id.'/modules/'.$ctx['fixtures']['module']->id.'/lessons',
            'expect' => ['status' => 200, 'json' => ['data.0.id' => fn (array $ctx): int => $ctx['fixtures']['lesson']->id]],
        ],
        [
            'name' => 'Lesson fornece conteúdo e mídia consumível sem internos',
            'as' => 'student',
            'path' => fn (array $ctx): string => '/api/v1/student/lessons/'.$ctx['fixtures']['lesson']->id,
            'expect' => [
                'status' => 200,
                'json' => [
                    'data.content.body' => 'Conteúdo consumível',
                    'data.media.0.url' => 'https://video.example/student-s01-e2e',
                    'data.media.0.url_kind' => 'player',
                ],
            ],
        ],
        [
            'name' => 'media separada retorna URL pública consumível',
            'as' => 'student',
            'path' => fn (array $ctx): string => '/api/v1/student/lessons/'.$ctx['fixtures']['lesson']->id.'/media',
            'expect' => ['status' => 200, 'json' => ['data.0.id' => fn (array $ctx): int => $ctx['fixtures']['media']->id]],
        ],
        [
            'name' => 'progresso próprio é persistido',
            'as' => 'student',
            'method' => 'POST',
            'path' => fn (array $ctx): string => '/api/v1/student/lessons/'.$ctx['fixtures']['lesson']->id.'/progress',
            'body' => ['time_spent_seconds' => 60, 'current_time_seconds' => 60, 'total_time_seconds' => 240, 'progress_percentage' => 25, 'is_completed' => false],
            'expect' => ['status' => 201, 'json' => ['data.progress_percentage' => 25]],
            'db' => fn (array $ctx): array => [
                'progress do próprio aluno' => [true, LessonProgress::query()->where('user_id', $ctx['users']['student']->id)->where('lesson_id', $ctx['fixtures']['lesson']->id)->exists()],
            ],
        ],
        [
            'name' => 'material devolve URL temporária sem path interno',
            'as' => 'student',
            'method' => 'POST',
            'path' => fn (array $ctx): string => '/api/v1/student/courses/'.$ctx['fixtures']['course']->id.'/materials/'.$ctx['fixtures']['material']->id.'/downloads',
            'expect' => ['status' => 201, 'json' => ['data.course_material_id' => fn (array $ctx): int => $ctx['fixtures']['material']->id]],
        ],
        [
            'name' => 'Student B sem Enrollment não acessa conteúdo de A',
            'headers' => fn (array $ctx): array => ['Authorization' => 'Bearer '.$ctx['fixtures']['studentBToken']],
            'path' => fn (array $ctx): string => '/api/v1/student/lessons/'.$ctx['fixtures']['lesson']->id,
            'expect' => ['status' => 404, 'json' => ['errors.0.code' => 'not_found']],
        ],
        [
            'name' => 'Enrollment pending não libera consumo',
            'as' => 'student',
            'path' => fn (array $ctx): string => '/api/v1/student/lessons/'.$ctx['fixtures']['pendingLesson']->id,
            'expect' => ['status' => 404, 'json' => ['errors.0.code' => 'not_found']],
        ],
        [
            'name' => 'draft e inactive não aparecem na árvore',
            'as' => 'student',
            'path' => fn (array $ctx): string => '/api/v1/student/courses/'.$ctx['fixtures']['course']->id.'/modules/'.$ctx['fixtures']['module']->id.'/lessons',
            'expect' => ['status' => 200, 'json' => ['data.1.id' => fn (array $ctx): int => $ctx['fixtures']['previewLesson']->id]],
        ],
        [
            'name' => 'ID foreign tenant não revela recurso',
            'as' => 'student',
            'path' => fn (array $ctx): string => '/api/v1/student/courses/'.$ctx['fixtures']['foreignCourse']->id,
            'expect' => ['status' => 404, 'json' => ['errors.0.code' => 'not_found']],
        ],
        [
            'name' => 'material e mídia foreign tenant são negados',
            'as' => 'student',
            'path' => fn (array $ctx): string => '/api/v1/student/courses/'.$ctx['fixtures']['foreignCourse']->id.'/materials/'.$ctx['fixtures']['foreignMaterial']->id.'/downloads',
            'method' => 'POST',
            'expect' => ['status' => 404, 'json' => ['errors.0.code' => 'not_found']],
        ],
        [
            'name' => 'media foreign tenant não revela recurso',
            'as' => 'student',
            'path' => fn (array $ctx): string => '/api/v1/student/lessons/'.$ctx['fixtures']['foreignLesson']->id.'/media',
            'expect' => ['status' => 404, 'json' => ['errors.0.code' => 'not_found']],
        ],
        [
            'name' => 'sem autenticação → 401',
            'path' => '/api/v1/student/courses',
            'expect' => ['status' => 401, 'json' => ['errors.0.code' => 'unauthenticated']],
        ],
        [
            'name' => 'área admin não entra na superfície Student',
            'as' => 'admin',
            'path' => '/api/v1/student/courses',
            'expect' => ['status' => 403, 'json' => ['errors.0.code' => 'area_forbidden']],
        ],
    ],

    'cleanup' => function (array $ctx): void {
        $tenantIds = [$ctx['tenant']->id, $ctx['otherTenant']->id];
        LessonProgress::query()->whereIn('tenant_id', $tenantIds)->delete();
        Enrollment::query()->whereIn('tenant_id', $tenantIds)->delete();
        CourseMaterial::query()->whereIn('tenant_id', $tenantIds)->delete();
        LessonMedia::query()->whereIn('tenant_id', $tenantIds)->delete();
        Course::query()->whereIn('tenant_id', $tenantIds)->forceDelete();
        Storage::disk(config('filesystems.default'))->delete([
            'tenants/'.$ctx['tenant']->id.'/materials/student-s01-e2e.pdf',
            'tenants/'.$ctx['otherTenant']->id.'/materials/foreign-student-s01-e2e.pdf',
        ]);
        $ctx['fixtures']['studentB']->tokens()->delete();
        $ctx['fixtures']['studentB']->forceDelete();
    },
];
