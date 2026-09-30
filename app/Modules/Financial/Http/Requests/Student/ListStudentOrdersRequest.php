<?php

namespace App\Modules\Financial\Http\Requests\Student;

use Illuminate\Foundation\Http\FormRequest;

class ListStudentOrdersRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'cursor' => ['nullable', 'string', 'max:512'],
            'tenant_id' => ['prohibited'],
            'user_id' => ['prohibited'],
            'per_page' => ['prohibited'],
            'sort' => ['prohibited'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'cursor.string' => 'O cursor deve ser uma string.',
            'cursor.max' => 'O cursor excede o tamanho permitido.',
            'tenant_id.prohibited' => 'Tenant é resolvido pelo contexto.',
            'user_id.prohibited' => 'Usuário é resolvido pelo contexto.',
            'per_page.prohibited' => 'O tamanho da página é definido pelo servidor.',
            'sort.prohibited' => 'A ordenação é definida pelo servidor.',
        ];
    }

    /** @return array<string, array{description: string, example: mixed}> */
    public function queryParameters(): array
    {
        return [
            'cursor' => [
                'description' => 'Cursor opaco retornado no link da próxima página.',
                'example' => 'eyJpZCI6MTUsIl9wb2ludHNUb05leHRJdGVtcyI6dHJ1ZX0',
            ],
        ];
    }
}
