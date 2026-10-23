<?php

namespace App\Http\Requests;

use App\Models\SourceFinancement;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SourceFinancementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $source = $this->route('source');

        return [
            'code' => ['required', 'string', 'max:30', Rule::unique('sources_financement', 'code')->ignore($source?->id)],
            'libelle' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::in(array_keys(SourceFinancement::TYPES))],
            'classification' => ['nullable', Rule::in(array_keys(SourceFinancement::CLASSIFICATIONS))],
            'partenaire_id' => ['nullable', 'exists:partenaires_financiers,id'],
            'bailleur' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'actif' => ['sometimes', 'boolean'],
        ];
    }
}
