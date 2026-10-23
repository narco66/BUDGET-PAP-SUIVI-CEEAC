<?php

namespace App\Http\Requests;

use App\Models\PartenaireFinancier;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PartenaireFinancierRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $partenaire = $this->route('partenaire');

        return [
            'code' => ['required', 'string', 'max:30', Rule::unique('partenaires_financiers', 'code')->ignore($partenaire?->id)],
            'libelle' => ['required', 'string', 'max:255'],
            'sigle' => ['nullable', 'string', 'max:30'],
            'type' => ['required', Rule::in(array_keys(PartenaireFinancier::TYPES))],
            'pays' => ['nullable', 'string', 'max:80'],
            'description' => ['nullable', 'string', 'max:2000'],
            'actif' => ['sometimes', 'boolean'],
        ];
    }
}
