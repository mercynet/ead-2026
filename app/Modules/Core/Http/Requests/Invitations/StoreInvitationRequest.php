<?php

namespace App\Modules\Core\Http\Requests\Invitations;

use App\Modules\Core\Models\Invitation;
use App\Shared\Http\ApiContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreInvitationRequest extends FormRequest
{
    public function authorize(): bool
    {
        app(ApiContext::class)->requiredTenant();

        return true;
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'email', 'max:255'],
            'role' => [
                'required',
                'string',
                Rule::in(Invitation::invitableRoles()),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'role.in' => 'Um convite só pode atribuir os papéis student ou instructor.',
        ];
    }

    /**
     * @return array<string, array<string, string>>
     */
    public function bodyParameters(): array
    {
        return [
            'email' => [
                'description' => 'Email do convidado (recebe o link de aceite)',
                'example' => 'novo.membro@example.com',
            ],
            'role' => [
                'description' => 'Papel a atribuir no aceite: student ou instructor',
                'example' => 'student',
            ],
        ];
    }
}
