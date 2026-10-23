<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProfilRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $id = $this->route('profil')?->id;

        return [
            'code' => ['required', 'string', 'max:50', Rule::unique('profils', 'code')->ignore($id)],
            'libelle' => ['required', 'string', 'max:150'],
            'type' => ['required', Rule::in(['institutionnel', 'metier', 'budgetaire', 'pap_gar', 'validation', 'consultation', 'audit'])],
            'description' => ['nullable', 'string'],
            'actif' => ['boolean'],
            'roles' => ['array'],
            'roles.*' => ['exists:roles,id'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['actif' => $this->boolean('actif')]);
    }
}
