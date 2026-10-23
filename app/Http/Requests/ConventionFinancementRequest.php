<?php

namespace App\Http\Requests;

use App\Models\ConventionFinancement;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ConventionFinancementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $convention = $this->route('convention');

        return [
            'partenaire_id' => ['required', 'exists:partenaires_financiers,id'],
            'source_financement_id' => ['nullable', 'exists:sources_financement,id'],
            'exercice_id' => ['nullable', 'exists:exercices,id'],
            'code' => ['required', 'string', 'max:40', Rule::unique('conventions_financement', 'code')->ignore($convention?->id)],
            'libelle' => ['required', 'string', 'max:255'],
            'reference_accord' => ['nullable', 'string', 'max:100'],
            'date_signature' => ['nullable', 'date'],
            'date_debut' => ['nullable', 'date'],
            'date_fin' => ['nullable', 'date', 'after_or_equal:date_debut'],
            'montant_accord' => ['required', 'numeric', 'min:0'],
            'montant_engage' => ['nullable', 'numeric', 'min:0'],
            'montant_execute' => ['nullable', 'numeric', 'min:0'],
            'montant_decaisse' => ['nullable', 'numeric', 'min:0'],
            'statut' => ['required', Rule::in(array_keys(ConventionFinancement::STATUTS))],
            'actif' => ['sometimes', 'boolean'],
        ];
    }
}
