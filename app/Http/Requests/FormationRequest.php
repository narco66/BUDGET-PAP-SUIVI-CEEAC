<?php

namespace App\Http\Requests;

use App\Models\Formation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class FormationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $id = $this->route('formation')?->id;

        return [
            'numero' => ['nullable', 'string', 'max:40', Rule::unique('formations', 'numero')->ignore($id)],
            'exercice_id' => ['required', 'exists:exercices,id'],
            'structure_id' => ['nullable', 'exists:structures,id'],
            'source_financement_id' => ['nullable', 'exists:sources_financement,id'],
            'engagement_id' => ['nullable', 'exists:engagements,id'],
            'intitule' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::in(array_keys(Formation::TYPES))],
            'thematique' => ['nullable', 'string', 'max:255'],
            'lieu' => ['nullable', 'string', 'max:255'],
            'date_debut' => ['nullable', 'date'],
            'date_fin' => ['nullable', 'date', 'after_or_equal:date_debut'],
            'nb_participants' => ['required', 'integer', 'min:0'],
            'nb_hommes' => ['required', 'integer', 'min:0'],
            'nb_femmes' => ['required', 'integer', 'min:0'],
            'cout' => ['required', 'numeric', 'min:0'],
            'niveau_satisfaction' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'impact' => ['nullable', 'string'],
            'statut' => ['required', Rule::in(array_keys(Formation::STATUTS))],
            'observations' => ['nullable', 'string'],
        ];
    }

    public function attributes(): array
    {
        return [
            'exercice_id' => 'exercice',
            'structure_id' => 'structure',
            'source_financement_id' => 'source de financement',
            'engagement_id' => 'engagement',
            'date_debut' => 'date de début',
            'date_fin' => 'date de fin',
            'nb_participants' => 'nombre de participants',
            'niveau_satisfaction' => 'niveau de satisfaction',
        ];
    }
}
