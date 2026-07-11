<?php

namespace App\Modules\Learning\Listeners;

use App\Modules\Financial\Events\OrderPaidEvent;
use App\Modules\Learning\Actions\Enrollment\EnrollStudentInCourseAction;
use App\Modules\Learning\Actions\Enrollment\EnrollStudentInCourseData;
use App\Modules\Learning\Models\Course;
use Illuminate\Database\Eloquent\Relations\Relation;

class EnrollStudentFromOrderPaidListener
{
    public function __construct(
        private readonly EnrollStudentInCourseAction $enrollStudentInCourseAction,
    ) {}

    public function handle(OrderPaidEvent $event): void
    {
        foreach ($event->items as $item) {
            if (! is_array($item)) {
                continue;
            }

            if (! isset($item['itemable_type'], $item['itemable_id'])) {
                continue;
            }

            $itemableType = (string) $item['itemable_type'];
            $resolvedType = Relation::getMorphedModel($itemableType) ?? $itemableType;

            if ($resolvedType !== Course::class) {
                continue;
            }

            $this->enrollStudentInCourseAction->handle(
                EnrollStudentInCourseData::orderPaid(
                    tenantId: $event->tenantId,
                    courseId: (int) $item['itemable_id'],
                    userId: $event->userId,
                )
            );
        }
    }
}
