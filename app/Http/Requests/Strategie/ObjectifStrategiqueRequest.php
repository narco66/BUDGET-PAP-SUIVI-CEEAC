<?php

namespace App\Http\Requests\Strategie;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ObjectifStrategiqueRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $id = $this->route('objectifStrategique')?->id;
        $planId = $this->input('plan_strategique_id', $this->route('objectifStrategique')?->plan_strategique_id);

        return [
            'plan_strategique_id' => ['required', 'exists:plans_strategiques,id'],
            'orientation_strategique_id' => ['nullable', 'exists:orientations_strategiques,id'],
            'code' => [
                'required', 'string', 'max:30',
                Rule::unique('objectifs_strategiques', 'code')
                    ->where('plan_strategique_id', $planId)
                    ->ignore($id),
            ],
            'intitule' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'ordre' => ['nullable', 'integer', 'min:0'],
            'statut' => ['required', 'in:actif,inactif,archive'],
        ];
    }
}
