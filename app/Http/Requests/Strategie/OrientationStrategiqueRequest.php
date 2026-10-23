<?php

namespace App\Http\Requests\Strategie;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class OrientationStrategiqueRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $id = $this->route('orientation')?->id;
        $planId = $this->input('plan_strategique_id', $this->route('orientation')?->plan_strategique_id);

        return [
            'plan_strategique_id' => ['required', 'exists:plans_strategiques,id'],
            'vision_id' => ['nullable', 'exists:visions,id'],
            'code' => [
                'required', 'string', 'max:30',
                Rule::unique('orientations_strategiques', 'code')
                    ->where(fn ($q) => $q->where('plan_strategique_id', $planId))
                    ->ignore($id),
            ],
            'intitule' => ['required', 'string', 'max:255'],
            'ordre' => ['nullable', 'integer', 'min:0'],
            'description' => ['nullable', 'string'],
            'statut' => ['required', 'in:actif,inactif,archive'],
        ];
    }
}
