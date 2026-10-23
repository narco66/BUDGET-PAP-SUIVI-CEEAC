<?php

namespace App\Http\Requests\Strategie;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndicateurStrategiqueRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $id = $this->route('indicateurStrategique')?->id;

        return [
            'objectif_strategique_id' => ['required', 'exists:objectifs_strategiques,id'],
            'code' => ['required', 'string', 'max:40', Rule::unique('indicateurs_strategiques', 'code')->ignore($id)],
            'libelle' => ['required', 'string', 'max:255'],
            'unite' => ['nullable', 'string', 'max:50'],
            'periodicite' => ['required', 'in:annuel,semestriel,trimestriel,mensuel'],
            'valeur_reference' => ['nullable', 'numeric'],
            'valeur_cible' => ['nullable', 'numeric'],
            'seuil_alerte' => ['nullable', 'numeric'],
            'indicateur_id' => ['nullable', 'exists:indicateurs,id'],
            'statut' => ['required', 'in:actif,inactif,archive'],
        ];
    }
}
