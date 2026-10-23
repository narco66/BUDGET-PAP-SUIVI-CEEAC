<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $id = $this->route('role')?->id;

        return [
            'name' => ['required', 'string', 'max:60', 'regex:/^[a-z0-9_]+$/', Rule::unique('roles', 'name')->ignore($id)],
            'libelle' => ['required', 'string', 'max:120'],
            'type' => ['required', Rule::in(['systeme', 'metier'])],
            'description' => ['nullable', 'string'],
            'permissions' => ['array'],
            'permissions.*' => ['exists:permissions,name'],
        ];
    }

    public function messages(): array
    {
        return ['name.regex' => 'Le code du rôle ne peut contenir que des minuscules, chiffres et underscores.'];
    }
}
