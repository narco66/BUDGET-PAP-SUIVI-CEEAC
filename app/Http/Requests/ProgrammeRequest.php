<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProgrammeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $id = $this->route('programme')?->id;

        return [
            'code' => ['required', 'string', 'max:30', Rule::unique('programmes', 'code')->ignore($id)],
            'intitule' => ['required', 'string', 'max:255'],
            'structure_id' => ['required', 'exists:structures,id'],
            'pilier_id' => ['nullable', 'exists:piliers,id'],
            'description' => ['nullable', 'string'],
            'actif' => ['boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['actif' => $this->boolean('actif')]);
    }
}
