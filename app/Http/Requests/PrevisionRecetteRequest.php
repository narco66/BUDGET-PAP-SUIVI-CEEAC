<?php

namespace App\Http\Requests;

use App\Models\CategorieRecette;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PrevisionRecetteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $prevision = $this->route('prevision');

        return [
            'exercice_id' => ['required', 'exists:exercices,id'],
            'reference' => [
                'required', 'string', 'max:60',
                Rule::unique('previsions_recettes', 'reference')
                    ->where(fn ($q) => $q->where('exercice_id', $this->exercice_id))
                    ->ignore($prevision?->id),
            ],
            'categorie_recette_id' => ['nullable', 'exists:categories_recettes,id'],
            'source_financement_id' => ['nullable', 'exists:sources_financement,id'],
            'etat_membre_id' => ['nullable', 'exists:etats_membres,id'],
            'rubrique_id' => ['nullable', 'exists:budget_rubriques,id'],
            'montant_prevu' => ['required', 'numeric', 'min:0'],
            'scenario' => ['nullable', 'string', 'max:30'],
            'statut' => ['nullable', Rule::in(array_keys(\App\Models\PrevisionRecette::STATUTS))],
            'observations' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function messages(): array
    {
        return [
            'reference.unique' => 'Cette référence de prévision existe déjà pour cet exercice.',
        ];
    }
}
