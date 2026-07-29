<?php

namespace App\Modules\Learning\Actions\Course;

use App\Modules\Learning\Models\Course;
use App\Modules\Learning\Models\CoursePriceHistory;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Str;

class UpdateCourseAction
{
    public function __construct(
        private readonly DatabaseManager $database,
    ) {}

    public function handle(Course $course, array $attributes, int $actorId): Course
    {
        return $this->database->transaction(function () use ($course, $attributes, $actorId): Course {
            $lockedCourse = Course::query()
                ->whereKey($course->id)
                ->where('tenant_id', $course->tenant_id)
                ->lockForUpdate()
                ->firstOrFail();
            $oldPriceCents = $lockedCourse->price_cents;

            if (isset($attributes['title'])) {
                $attributes['slug'] = Str::slug($attributes['title']);
            }

            $lockedCourse->fill($attributes);
            $lockedCourse->save();

            if ($lockedCourse->wasChanged('price_cents')) {
                CoursePriceHistory::query()->create([
                    'tenant_id' => $lockedCourse->tenant_id,
                    'course_id' => $lockedCourse->id,
                    'changed_by_user_id' => $actorId,
                    'old_price_cents' => $oldPriceCents,
                    'new_price_cents' => $lockedCourse->price_cents,
                    'changed_at' => now(),
                ]);
            }

            return $lockedCourse->fresh();
        });
    }
}
