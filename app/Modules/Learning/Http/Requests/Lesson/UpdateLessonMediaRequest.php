<?php

namespace App\Modules\Learning\Http\Requests\Lesson;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateLessonMediaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'media_type' => ['sometimes', 'string', Rule::in(['video', 'audio', 'document', 'text', 'embed'])],
            'provider' => ['sometimes', 'nullable', 'string', Rule::in(['youtube', 'vimeo', 's3', 'internal', 'embed'])],
            'provider_ref' => ['sometimes', 'nullable', 'string', 'max:255'],
            'url' => ['sometimes', 'nullable', 'url', 'max:2048'],
            'content' => ['sometimes', 'nullable', 'string'],
            'duration_seconds' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'sort_order' => ['sometimes', 'integer', 'min:1'],
            'is_active' => ['sometimes', 'boolean'],
            'metadata' => ['sometimes', 'nullable', 'array'],
        ];
    }

    public function messages(): array
    {
        return [
            'media_type.in' => 'Media type must be one of: video, audio, document, text or embed.',
            'provider.in' => 'Provider must be one of: youtube, vimeo, s3, internal or embed.',
            'url.url' => 'Media URL must be a valid URL.',
            'duration_seconds.integer' => 'Duration must be a valid integer.',
            'duration_seconds.min' => 'Duration must be at least 1 second.',
            'sort_order.integer' => 'Sort order must be a valid integer.',
            'sort_order.min' => 'Sort order must be at least 1.',
            'metadata.array' => 'Metadata must be an object.',
        ];
    }
}
