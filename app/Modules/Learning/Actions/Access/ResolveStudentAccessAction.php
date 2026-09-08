<?php

namespace App\Modules\Learning\Actions\Access;

use App\Modules\Learning\Models\Course;
use App\Modules\Learning\Models\Enrollment;
use App\Modules\Learning\Models\Lesson;
use App\Shared\Http\ApiContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class ResolveStudentAccessAction
{
    public function activeEnrollment(ApiContext $context, int $courseId): ?Enrollment
    {
        return Enrollment::query()
            ->forTenantUserCourse(
                $context->requiredTenant()->id,
                $context->requiredUser()->id,
                $courseId,
            )
            ->where('status', 'active')
            ->where(function ($expiryQuery): void {
                $expiryQuery->whereNull('access_expires_at')->orWhere('access_expires_at', '>', now());
            })
            ->latest('id')
            ->first();
    }

    public function requireCourse(ApiContext $context, int $courseId): Course
    {
        $course = Course::query()
            ->where('tenant_id', $context->requiredTenant()->id)
            ->whereKey($courseId)
            ->where('status', 'published')
            ->where('is_active', true)
            ->whereHas('enrollments', function ($query) use ($context): void {
                $query
                    ->where('tenant_id', $context->requiredTenant()->id)
                    ->where('user_id', $context->requiredUser()->id)
                    ->where('status', 'active')
                    ->where(function ($expiryQuery): void {
                        $expiryQuery->whereNull('access_expires_at')->orWhere('access_expires_at', '>', now());
                    });
            })
            ->with([
                'categories',
                'enrollments' => function ($query) use ($context): void {
                    $query
                        ->where('tenant_id', $context->requiredTenant()->id)
                        ->where('user_id', $context->requiredUser()->id)
                        ->where('status', 'active')
                        ->where(function ($expiryQuery): void {
                            $expiryQuery->whereNull('access_expires_at')->orWhere('access_expires_at', '>', now());
                        })
                        ->latest('id');
                },
            ])
            ->firstOrFail();

        $course->setRelation('studentEnrollment', $course->enrollments->first());

        return $course;
    }

    /**
     * @return array{lesson: Lesson, enrollment: Enrollment|null, preview: bool}
     */
    public function requireLesson(ApiContext $context, int $lessonId, bool $allowPreview = true): array
    {
        $lesson = Lesson::query()
            ->where('tenant_id', $context->requiredTenant()->id)
            ->whereKey($lessonId)
            ->where('status', 'published')
            ->where('is_active', true)
            ->whereHas('courseModule', function (Builder $moduleQuery) use ($context): void {
                $moduleQuery
                    ->where('tenant_id', $context->requiredTenant()->id)
                    ->whereHas('course', function (Builder $courseQuery) use ($context): void {
                        $courseQuery
                            ->where('tenant_id', $context->requiredTenant()->id)
                            ->where('status', 'published')
                            ->where('is_active', true);
                    });
            })
            ->with([
                'courseModule.course',
                'media' => fn ($query) => $query
                    ->where('tenant_id', $context->requiredTenant()->id)
                    ->where('is_active', true)
                    ->orderBy('sort_order')
                    ->orderBy('id'),
            ])
            ->firstOrFail();

        $course = $lesson->courseModule->course;
        $enrollment = $this->activeEnrollment($context, $course->id);
        $preview = $enrollment === null && (bool) $lesson->getAttribute('is_free');

        if ($enrollment === null && (! $allowPreview || ! $preview)) {
            throw (new ModelNotFoundException)->setModel(Lesson::class, [$lessonId]);
        }

        return [
            'lesson' => $lesson,
            'enrollment' => $enrollment,
            'preview' => $preview,
        ];
    }
}
