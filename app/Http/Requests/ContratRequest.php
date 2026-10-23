<?php

namespace App\Http\Requests;

use App\Models\Contrat;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ContratRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $id = $this->route('contrat')?->id;

        return [
            'numero' => ['nullable', 'string', 'max:40', Rule::unique('contrats', 'numero')->ignore($id)],
            'exercice_id' => ['required', 'exists:exercices,id'],
            'marche_id' => ['nullable', 'exists:marches,id'],
            'engagement_id' => ['nullable', 'exists:engagements,id'],
            'structure_id' => ['nullable', 'exists:structures,id'],
            'objet' => ['required', 'string', 'max:255'],
            'titulaire' => ['required', 'string', 'max:255'],
            'type_contrat' => ['required', Rule::in(array_keys(Contrat::TYPES))],
            'montant' => ['required', 'numeric', 'min:0'],
            'date_signature' => ['nullable', 'date'],
            'date_debut' => ['nullable', 'date'],
            'date_fin' => ['nullable', 'date', 'after_or_equal:date_debut'],
            'statut' => ['required', Rule::in(array_keys(Contrat::STATUTS))],
            'observations' => ['nullable', 'string'],
        ];
    }

    public function attributes(): array
    {
        return [
            'exercice_id' => 'exercice',
            'marche_id' => 'marché',
            'engagement_id' => 'engagement',
            'structure_id' => 'structure',
            'type_contrat' => 'type de contrat',
            'date_signature' => 'date de signature',
            'date_debut' => 'date de début',
            'date_fin' => 'date de fin',
        ];
    }
}
