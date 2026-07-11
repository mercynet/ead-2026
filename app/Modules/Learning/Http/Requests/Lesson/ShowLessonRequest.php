<?php

namespace App\Modules\Learning\Http\Requests\Lesson;

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
