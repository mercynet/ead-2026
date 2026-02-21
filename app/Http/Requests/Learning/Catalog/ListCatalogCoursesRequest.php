<?php

namespace App\Http\Requests\Learning\Catalog;

use App\Exceptions\TenantContextRequiredException;
use App\Http\Context\ApiContext;
use Illuminate\Foundation\Http\FormRequest;

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
        ];
    }

    public function messages(): array
    {
        return [
            'category.string' => 'Category filter must be a string.',
            'is_free.boolean' => 'The is_free filter must be true or false.',
            'is_featured.boolean' => 'The is_featured filter must be true or false.',
        ];
    }
}
