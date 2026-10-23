<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AppelFondsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'exercice_id' => ['required', 'exists:exercices,id'],
            'etat_membre_id' => ['nullable', 'exists:etats_membres,id'],
            'source_financement_id' => ['nullable', 'exists:sources_financement,id'],
            'reference' => ['required', 'string', 'max:60', Rule::unique('appels_fonds', 'reference')],
            'objet' => ['required', 'string', 'max:255'],
            'montant_appele' => ['required', 'numeric', 'min:0.01'],
            'date_emission' => ['required', 'date'],
            'date_echeance' => ['nullable', 'date', 'after_or_equal:date_emission'],
            'taux_penalite' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'observations' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function messages(): array
    {
        return [
            'reference.unique' => 'Cette référence d\'appel de fonds existe déjà.',
        ];
    }
}
