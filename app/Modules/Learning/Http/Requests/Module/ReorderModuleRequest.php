<?php

namespace App\Modules\Learning\Http\Requests\Module;

use App\Modules\Core\Models\Tenant;
use App\Modules\Learning\Models\Course;
use App\Modules\Learning\Models\CourseModule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class ReorderModuleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $tenant = app(Tenant::class);

        return [
            'course_id' => [
                'required',
                'integer',
                Rule::exists('courses', 'id')
                    ->where('tenant_id', $tenant->id)
                    ->whereNull('deleted_at'),
            ],
            'module_ids' => ['required', 'array', 'min:1'],
            'module_ids.*' => ['integer', 'distinct'],
        ];
    }

    public function messages(): array
    {
        return [
            'course_id.required' => 'Course is required.',
            'course_id.integer' => 'Course must be a valid integer.',
            'course_id.exists' => 'Course must belong to the current tenant and be active.',
            'module_ids.required' => 'Module list is required.',
            'module_ids.array' => 'Module list must be an array.',
            'module_ids.min' => 'Module list must contain at least one module.',
            'module_ids.*.integer' => 'Module ids must be valid integers.',
            'module_ids.*.distinct' => 'Module ids must be distinct.',
        ];
    }

    public function bodyParameters(): array
    {
        return [
            'course_id' => [
                'description' => 'ID do curso do tenant atual cujos módulos serão reordenados.',
                'example' => 10,
            ],
            'module_ids' => [
                'description' => 'Lista completa e ordenada dos IDs dos módulos do curso.',
                'example' => [3, 1, 2],
            ],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $tenant = app(Tenant::class);
            $courseId = (int) $this->input('course_id');
            $moduleIds = $this->input('module_ids', []);

            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $course = Course::query()
                ->where('tenant_id', $tenant->id)
                ->whereKey($courseId)
                ->whereNull('deleted_at')
                ->first();

            if ($course === null) {
                return;
            }

            $currentModuleIds = CourseModule::query()
                ->where('tenant_id', $tenant->id)
                ->where('course_id', $course->id)
                ->pluck('id')
                ->all();

            $providedModuleIds = array_map('intval', is_array($moduleIds) ? $moduleIds : []);

            $missingModuleIds = array_values(array_diff($currentModuleIds, $providedModuleIds));
            $extraModuleIds = array_values(array_diff($providedModuleIds, $currentModuleIds));

            if ($missingModuleIds !== [] || $extraModuleIds !== []) {
                $validator->errors()->add(
                    'module_ids',
                    'Module list must contain exactly all modules from the course in the desired order.'
                );
            }
        });
    }
}
