<?php

namespace App\Modules\Assessment\Http\Resources\Instructor;

use App\Modules\Assessment\Models\QuizQuestion;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin QuizQuestion
 *
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Model> $categories
 */
class QuestionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var QuizQuestion $question */
        $question = $this->resource;

        return [
            'id' => $question->getAttribute('id'),
            'question' => $question->getAttribute('question'),
            'type' => $question->getAttribute('type'),
            'options' => $question->getAttribute('options'),
            'explanation' => $question->getAttribute('explanation'),
            'points' => $question->getAttribute('points'),
            'is_active' => $question->getAttribute('is_active'),
            'categories' => $this->whenLoaded('categories', function () use ($question): array {
                /** @var \Illuminate\Database\Eloquent\Collection<int, Model> $categories */
                $categories = $question->categories;

                return $categories->map(fn (Model $category): array => [
                    'id' => $category->getAttribute('id'),
                    'name' => $category->getAttribute('name'),
                    'slug' => $category->getAttribute('slug'),
                ])->all();
            }),
        ];
    }
}
