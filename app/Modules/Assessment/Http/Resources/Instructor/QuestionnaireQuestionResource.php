<?php

namespace App\Modules\Assessment\Http\Resources\Instructor;

use App\Modules\Assessment\Models\QuestionnaireQuestion;
use App\Modules\Assessment\Models\QuizQuestion;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin QuestionnaireQuestion
 *
 * @property int $sort_order
 * @property-read QuizQuestion|null $question
 */
class QuestionnaireQuestionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var QuestionnaireQuestion $pivot */
        $pivot = $this->resource;

        /** @var QuizQuestion|null $question */
        $question = $pivot->question;

        return [
            'id' => $question?->getAttribute('id'),
            'sort_order' => $pivot->getAttribute('sort_order'),
            'question' => QuestionResource::make($question),
        ];
    }
}
