<?php

namespace App\Modules\Learning\Actions\Course;

use App\Modules\Learning\Actions\Access\EvaluateCourseAccessAction;
use App\Modules\Learning\Models\Course;
use App\Modules\Learning\Models\Rating;
use App\Shared\Exceptions\AccessDeniedException;
use App\Shared\Http\ApiContext;
use Illuminate\Support\Facades\DB;

class StoreCourseRatingAction
{
    public function __construct(
        private readonly EvaluateCourseAccessAction $evaluateCourseAccessAction,
        private readonly SyncRatingStatsAction $syncRatingStatsAction,
    ) {}

    public function handle(ApiContext $context, Course $course, array $attributes = []): Rating
    {
        if (! $context->requiredUser()->isStudent()) {
            throw AccessDeniedException::make('course', $course->id);
        }

        if (! $this->evaluateCourseAccessAction->canViewCourse($course)) {
            throw AccessDeniedException::make('course', $course->id);
        }

        if (! $this->evaluateCourseAccessAction->canAccessPaidContent($course, $context)) {
            throw AccessDeniedException::make('course', $course->id);
        }

        return DB::transaction(function () use ($context, $course, $attributes): Rating {
            Course::query()
                ->whereKey($course->id)
                ->lockForUpdate()
                ->firstOrFail();

            $rating = Rating::query()
                ->where('tenant_id', $context->requiredTenant()->id)
                ->where('user_id', $context->requiredUser()->id)
                ->where('rateable_type', $course->getMorphClass())
                ->where('rateable_id', $course->id)
                ->lockForUpdate()
                ->first();

            if ($rating === null) {
                $rating = new Rating([
                    'tenant_id' => $context->requiredTenant()->id,
                    'user_id' => $context->requiredUser()->id,
                    'rateable_type' => $course->getMorphClass(),
                    'rateable_id' => $course->id,
                ]);
            }

            $rating->fill([
                'stars' => $attributes['stars'],
                'reaction' => $attributes['reaction'] ?? null,
            ]);
            $rating->save();

            $this->syncRatingStatsAction->handle($course);

            return $rating->loadMissing('rateable.ratingStats');
        });
    }
}
