<?php

namespace App\Modules\Learning\Http\Requests\Course;

use Illuminate\Foundation\Http\FormRequest;

class StoreMaterialDownloadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [];
    }

    public function bodyParameters(): array
    {
        return [];
    }
}
