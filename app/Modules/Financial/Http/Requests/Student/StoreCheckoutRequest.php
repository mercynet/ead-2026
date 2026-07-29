<?php

namespace App\Modules\Financial\Http\Requests\Student;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreCheckoutRequest extends FormRequest
{
    /** @bodyParam course_id integer required ID do curso publicado e ativo. Example: 1 */
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'course_id' => ['required', 'integer', 'min:1'],
            'tenant_id' => ['prohibited'],
            'user_id' => ['prohibited'],
            'price' => ['prohibited'],
            'price_cents' => ['prohibited'],
            'amount' => ['prohibited'],
            'amount_cents' => ['prohibited'],
            'gateway' => ['prohibited'],
            'gateway_slug' => ['prohibited'],
            'status' => ['prohibited'],
            'origin_type' => ['prohibited'],
            'snapshot' => ['prohibited'],
            'discounts' => ['prohibited'],
            'metadata' => ['prohibited'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            foreach (array_keys($this->all()) as $field) {
                if ($field !== 'course_id') {
                    $validator->errors()->add($field, 'Campo não permitido.');
                }
            }

            $key = $this->header('Idempotency-Key');

            if (! is_string($key) || ! preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[1-8][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $key)) {
                $validator->errors()->add('Idempotency-Key', 'Cabeçalho Idempotency-Key deve ser UUID válido.');
            }
        });
    }

    public function messages(): array
    {
        return [
            'course_id.required' => 'Curso é obrigatório.',
            'course_id.integer' => 'Curso deve ser um identificador válido.',
            '*.prohibited' => 'Campo não permitido.',
        ];
    }
}
