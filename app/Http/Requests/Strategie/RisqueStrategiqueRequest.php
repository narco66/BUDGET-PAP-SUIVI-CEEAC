<?php

namespace App\Http\Requests\Strategie;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RisqueStrategiqueRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $id = $this->route('risqueStrategique')?->id;
        $planId = $this->input('plan_strategique_id', $this->route('risqueStrategique')?->plan_strategique_id);

        return [
            'plan_strategique_id' => ['required', 'exists:plans_strategiques,id'],
            'projet_id' => ['nullable', 'exists:projets,id'],
            'code' => [
                'required', 'string', 'max:30',
                Rule::unique('risques_strategiques', 'code')
                    ->where('plan_strategique_id', $planId)
                    ->ignore($id),
            ],
            'description' => ['required', 'string'],
            'probabilite' => ['required', 'in:faible,moyenne,elevee'],
            'impact' => ['required', 'in:faible,moyen,eleve'],
            'mitigation' => ['nullable', 'string'],
            'responsable_user_id' => ['nullable', 'exists:users,id'],
            'statut' => ['required', 'in:identifie,en_traitement,mitige,cloture'],
        ];
    }
}
