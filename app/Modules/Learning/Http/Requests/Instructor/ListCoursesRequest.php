<?php

namespace App\Modules\Learning\Http\Requests\Instructor;

use Illuminate\Foundation\Http\FormRequest;

class ListCoursesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => ['prohibited'],
            'is_active' => ['prohibited'],
        ];
    }

    public function queryParameters(): array
    {
        return [];
    }
}
