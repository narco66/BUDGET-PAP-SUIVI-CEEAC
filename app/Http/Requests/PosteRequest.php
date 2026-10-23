<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PosteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $id = $this->route('poste')?->id;

        return [
            'code' => ['required', 'string', 'max:40', Rule::unique('postes', 'code')->ignore($id)],
            'libelle' => ['required', 'string', 'max:255'],
            'fonction_id' => ['nullable', 'exists:fonctions,id'],
            'description' => ['nullable', 'string'],
            'actif' => ['boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['actif' => $this->boolean('actif')]);
    }
}
