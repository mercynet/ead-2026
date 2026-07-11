<?php

namespace App\Modules\Learning\Http\Requests\Lesson;

use App\Modules\Core\Models\Tenant;
use App\Modules\Learning\Models\CourseModule;
use App\Modules\Learning\Models\Lesson;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class ReorderLessonRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $tenant = app(Tenant::class);

        return [
            'course_module_id' => [
                'required',
                'integer',
                Rule::exists('course_modules', 'id')
                    ->where('tenant_id', $tenant->id),
            ],
            'lesson_ids' => ['required', 'array', 'min:1'],
            'lesson_ids.*' => ['integer', 'distinct'],
        ];
    }

    public function messages(): array
    {
        return [
            'course_module_id.required' => 'Module is required.',
            'course_module_id.integer' => 'Module must be a valid integer.',
            'course_module_id.exists' => 'Module must belong to the current tenant and be active.',
            'lesson_ids.required' => 'Lesson list is required.',
            'lesson_ids.array' => 'Lesson list must be an array.',
            'lesson_ids.min' => 'Lesson list must contain at least one lesson.',
            'lesson_ids.*.integer' => 'Lesson ids must be valid integers.',
            'lesson_ids.*.distinct' => 'Lesson ids must be distinct.',
        ];
    }

    public function bodyParameters(): array
    {
        return [
            'course_module_id' => [
                'description' => 'ID do módulo do tenant atual cujas aulas serão reordenadas.',
                'example' => 10,
            ],
            'lesson_ids' => [
                'description' => 'Lista completa e ordenada dos IDs das aulas do módulo.',
                'example' => [3, 1, 2],
            ],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $tenant = app(Tenant::class);
            $moduleId = (int) $this->input('course_module_id');
            $lessonIds = $this->input('lesson_ids', []);

            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $module = CourseModule::query()
                ->where('tenant_id', $tenant->id)
                ->whereKey($moduleId)
                ->first();

            if ($module === null) {
                return;
            }

            $currentLessonIds = Lesson::query()
                ->where('tenant_id', $tenant->id)
                ->where('course_module_id', $module->id)
                ->whereNull('deleted_at')
                ->pluck('id')
                ->all();

            $providedLessonIds = array_map('intval', is_array($lessonIds) ? $lessonIds : []);

            $missingLessonIds = array_values(array_diff($currentLessonIds, $providedLessonIds));
            $extraLessonIds = array_values(array_diff($providedLessonIds, $currentLessonIds));

            if ($missingLessonIds !== [] || $extraLessonIds !== []) {
                $validator->errors()->add(
                    'lesson_ids',
                    'Lesson list must contain exactly all lessons from the module in the desired order.'
                );
            }
        });
    }
}
