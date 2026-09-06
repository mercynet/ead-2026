<?php

namespace App\Modules\Learning\Actions\Lesson;

use App\Modules\Learning\Models\LessonMedia;
use App\Shared\Http\ApiContext;
use Illuminate\Pagination\CursorPaginator;

class ListLessonMediaAction
{
    public function handle(ApiContext $context, int $lessonId): CursorPaginator
    {
        return LessonMedia::query()
            ->where('tenant_id', $context->requiredTenant()->id)
            ->where('lesson_id', $lessonId)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->cursorPaginate(15);
    }
}
