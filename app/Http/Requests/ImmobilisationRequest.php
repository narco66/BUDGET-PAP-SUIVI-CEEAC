<?php

namespace App\Http\Requests;

use App\Models\Immobilisation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ImmobilisationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $id = $this->route('immobilisation')?->id;

        return [
            'numero' => ['nullable', 'string', 'max:40', Rule::unique('immobilisations', 'numero')->ignore($id)],
            'exercice_id' => ['required', 'exists:exercices,id'],
            'structure_id' => ['nullable', 'exists:structures,id'],
            'engagement_id' => ['nullable', 'exists:engagements,id'],
            'categorie' => ['required', Rule::in(array_keys(Immobilisation::CATEGORIES))],
            'designation' => ['required', 'string', 'max:255'],
            'marque' => ['nullable', 'string', 'max:120'],
            'numero_serie' => ['nullable', 'string', 'max:120'],
            'localisation' => ['nullable', 'string', 'max:255'],
            'affectation' => ['nullable', 'string', 'max:255'],
            'date_acquisition' => ['required', 'date'],
            'valeur_acquisition' => ['required', 'numeric', 'min:0'],
            'duree_amortissement_ans' => ['required', 'integer', 'min:1', 'max:50'],
            'compte_syscohada' => ['nullable', 'string', 'max:20'],
            'statut' => ['required', Rule::in(array_keys(Immobilisation::STATUTS))],
            'observations' => ['nullable', 'string'],
        ];
    }
}
