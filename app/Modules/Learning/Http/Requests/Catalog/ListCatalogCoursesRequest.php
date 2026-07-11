<?php

namespace App\Modules\Learning\Http\Requests\Catalog;

use App\Shared\Exceptions\TenantContextRequiredException;
use App\Shared\Http\ApiContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListCatalogCoursesRequest extends FormRequest
{
    public function authorize(): bool
    {
        $context = app(ApiContext::class);

        if ($context->tenant === null && $context->user === null) {
            throw TenantContextRequiredException::make();
        }

        return true;
    }

    public function rules(): array
    {
        return [
            'category' => ['nullable', 'string', 'max:120'],
            'is_free' => ['nullable', 'boolean'],
            'is_featured' => ['nullable', 'boolean'],
            'sort' => ['nullable', Rule::in(['top_rated'])],
            'min_ratings' => ['nullable', 'integer', 'min:1'],
        ];
    }

    public function messages(): array
    {
        return [
            'category.string' => 'Category filter must be a string.',
            'is_free.boolean' => 'The is_free filter must be true or false.',
            'is_featured.boolean' => 'The is_featured filter must be true or false.',
            'sort.in' => 'The sort parameter must be top_rated when provided.',
            'min_ratings.integer' => 'The min_ratings parameter must be an integer.',
            'min_ratings.min' => 'The min_ratings parameter must be at least 1.',
        ];
    }

    /**
     * Query parameters for Scribe documentation.
     */
    public function queryParameters(): array
    {
        return [
            'category' => [
                'description' => 'Filtrar cursos por categoria (slug)',
                'example' => 'programacao',
            ],
            'is_free' => [
                'description' => 'Filtrar cursos gratuitos',
                'example' => true,
            ],
            'is_featured' => [
                'description' => 'Filtrar cursos em destaque',
                'example' => true,
            ],
            'sort' => [
                'description' => 'Ordenar explicitamente por ranking de avaliações',
                'example' => 'top_rated',
            ],
            'min_ratings' => [
                'description' => 'Exigir um mínimo de avaliações para entrar no ranking',
                'example' => 5,
            ],
        ];
    }
}
