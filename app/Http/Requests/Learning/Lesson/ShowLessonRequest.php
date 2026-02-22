<?php

namespace App\Http\Requests\Learning\Lesson;

use Illuminate\Foundation\Http\FormRequest;

class ShowLessonRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [];
    }
}
