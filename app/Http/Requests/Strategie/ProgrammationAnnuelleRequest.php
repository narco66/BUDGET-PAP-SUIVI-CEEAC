<?php

namespace App\Http\Requests\Strategie;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProgrammationAnnuelleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $id = $this->route('programmationAnnuelle')?->id;
        $pluriId = $this->input('programmation_pluriannuelle_id', $this->route('programmationAnnuelle')?->programmation_pluriannuelle_id);

        return [
            'programmation_pluriannuelle_id' => ['required', 'exists:programmations_pluriannuelles,id'],
            'exercice_id' => [
                'required', 'exists:exercices,id',
                Rule::unique('programmations_annuelles', 'exercice_id')
                    ->where(fn ($q) => $q->where('programmation_pluriannuelle_id', $pluriId))
                    ->ignore($id),
            ],
            'statut' => ['required', 'in:brouillon,actif,archive'],
            'activites_prevues' => ['nullable', 'string'],
            'ressources_prevues' => ['nullable', 'string'],
            'indicateurs_prevus' => ['nullable', 'string'],
            'pap_id' => ['nullable', 'exists:paps,id'],
        ];
    }
}
