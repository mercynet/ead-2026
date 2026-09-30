<?php

namespace App\Modules\Financial\Http\Requests\Student;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;
use JsonException;

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
            'cursor.shape' => 'O cursor é inválido ou expirou.',
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

    protected function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $cursor = $this->input('cursor');

            if ($cursor === null || $validator->errors()->has('cursor') || $this->hasValidCursorShape((string) $cursor)) {
                return;
            }

            $validator->errors()->add('cursor', 'O cursor é inválido ou expirou.');
        });
    }

    private function hasValidCursorShape(string $cursor): bool
    {
        $padding = strlen($cursor) % 4;
        $decoded = base64_decode(strtr($cursor.str_repeat('=', $padding === 0 ? 0 : 4 - $padding), '-_', '+/'), true);

        if ($decoded === false) {
            return false;
        }

        try {
            $parameters = json_decode($decoded, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return false;
        }

        if (! is_array($parameters) || ! array_key_exists('_pointsToNextItems', $parameters) || ! is_bool($parameters['_pointsToNextItems'])) {
            return false;
        }

        $id = $parameters['id'] ?? null;

        return (is_int($id) && $id >= 0) || (is_string($id) && ctype_digit($id));
    }
}
