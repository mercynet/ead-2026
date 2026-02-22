<?php

namespace App\Actions\Assessment\Question;

use App\Http\Context\ApiContext;
use App\Models\QuizQuestion;
use Illuminate\Http\Request;
use Illuminate\Pagination\CursorPaginator;

/**
 * List all questions for the tenant.
 *
 * @apiResource App\Http\Resources\Assessment\QuestionResource
 *
 * @apiResourceCollection Illuminate\Pagination\CursorPaginator<App\Http\Resources\Assessment\QuestionResource>
 */
class ListQuestionsAction
{
    public function handle(Request $request, ApiContext $context): CursorPaginator
    {
        $query = QuizQuestion::query()
            ->with(['categories:id,name,slug', 'instructor:id,name,email'])
            ->orderBy('id');

        if ($context->tenant !== null) {
            $query->where('tenant_id', $context->tenant->id);
        }

        $categoryId = $request->query('category_id');
        if ($categoryId !== null) {
            $query->whereHas('categories', function ($q) use ($categoryId): void {
                $q->where('categories.id', $categoryId);
            });
        }

        $isActive = $request->query('is_active');
        if ($isActive !== null) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        return $query->cursorPaginate(15);
    }
}
