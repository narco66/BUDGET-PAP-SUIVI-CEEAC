<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AccessScopeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'scopes' => ['array'],
            'scopes.*.type' => ['required', Rule::in([
                'global', 'departement', 'direction', 'service', 'programme', 'exercice',
                'lecture_seule', 'propres_dossiers', 'a_valider',
            ])],
            'scopes.*.scope_id' => ['nullable', 'integer'],
        ];
    }
}
