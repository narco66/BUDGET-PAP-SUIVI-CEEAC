<?php

namespace App\Http\Requests\Strategie;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProjetStrategiqueRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $id = $this->route('projet')?->id;
        $programmeId = $this->input('programme_strategique_id', $this->route('projet')?->programme_strategique_id);

        return [
            'programme_strategique_id' => ['required', 'exists:programmes_strategiques,id'],
            'code' => [
                'required', 'string', 'max:30',
                Rule::unique('projets', 'code')
                    ->where(fn ($q) => $q->where('programme_strategique_id', $programmeId))
                    ->ignore($id),
            ],
            'intitule' => ['required', 'string', 'max:255'],
            'responsable_user_id' => ['required', 'exists:users,id'],
            'statut' => ['required', 'in:planifie,en_cours,suspendu,acheve,archive'],
            'cycle_vie' => ['required', 'in:initiation,planification,execution,cloture'],
            'date_debut' => ['nullable', 'date'],
            'date_fin' => ['nullable', 'date', 'after_or_equal:date_debut'],
            'budget_previsionnel' => ['nullable', 'numeric', 'min:0'],
            'budget_engage' => ['nullable', 'numeric', 'min:0'],
            'budget_consomme' => ['nullable', 'numeric', 'min:0'],
            'pilier_id' => ['nullable', 'exists:piliers,id'],
            'description' => ['nullable', 'string'],
        ];
    }
}
