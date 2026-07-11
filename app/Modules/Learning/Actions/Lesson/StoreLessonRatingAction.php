<?php

namespace App\Modules\Learning\Actions\Lesson;

use App\Modules\Learning\Actions\Access\EvaluateCourseAccessAction;
use App\Modules\Learning\Actions\Course\SyncRatingStatsAction;
use App\Modules\Learning\Models\Lesson;
use App\Modules\Learning\Models\Rating;
use App\Shared\Exceptions\AccessDeniedException;
use App\Shared\Http\ApiContext;
use Illuminate\Support\Facades\DB;

class StoreLessonRatingAction
{
    public function __construct(
        private readonly EvaluateCourseAccessAction $evaluateCourseAccessAction,
        private readonly SyncRatingStatsAction $syncRatingStatsAction,
    ) {}

    public function handle(ApiContext $context, Lesson $lesson, array $attributes = []): Rating
    {
        if (! $context->requiredUser()->isStudent()) {
            throw AccessDeniedException::make('lesson', $lesson->id);
        }

        if (! $this->evaluateCourseAccessAction->canAccessLesson($lesson, $context)) {
            throw AccessDeniedException::make('lesson', $lesson->id);
        }

        return DB::transaction(function () use ($context, $lesson, $attributes): Rating {
            Lesson::query()
                ->whereKey($lesson->id)
                ->lockForUpdate()
                ->firstOrFail();

            $rating = Rating::query()
                ->where('tenant_id', $context->requiredTenant()->id)
                ->where('user_id', $context->requiredUser()->id)
                ->where('rateable_type', $lesson->getMorphClass())
                ->where('rateable_id', $lesson->id)
                ->lockForUpdate()
                ->first();

            if ($rating === null) {
                $rating = new Rating([
                    'tenant_id' => $context->requiredTenant()->id,
                    'user_id' => $context->requiredUser()->id,
                    'rateable_type' => $lesson->getMorphClass(),
                    'rateable_id' => $lesson->id,
                ]);
            }

            $rating->fill([
                'stars' => $attributes['stars'],
                'reaction' => $attributes['reaction'] ?? null,
            ]);
            $rating->save();

            $this->syncRatingStatsAction->handle($lesson);

            return $rating->loadMissing('rateable.ratingStats');
        });
    }
}
