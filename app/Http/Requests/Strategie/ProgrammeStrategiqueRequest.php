<?php

namespace App\Http\Requests\Strategie;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProgrammeStrategiqueRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $id = $this->route('programmeStrategique')?->id;
        $orientationId = $this->input('orientation_strategique_id', $this->route('programmeStrategique')?->orientation_strategique_id);

        return [
            'orientation_strategique_id' => ['required', 'exists:orientations_strategiques,id'],
            'code' => [
                'required', 'string', 'max:30',
                Rule::unique('programmes_strategiques', 'code')
                    ->where(fn ($q) => $q->where('orientation_strategique_id', $orientationId))
                    ->ignore($id),
            ],
            'intitule' => ['required', 'string', 'max:255'],
            'responsable_user_id' => ['nullable', 'exists:users,id'],
            'date_debut' => ['nullable', 'date'],
            'date_fin' => ['nullable', 'date', 'after_or_equal:date_debut'],
            'budget_previsionnel' => ['nullable', 'numeric', 'min:0'],
            'objectifs' => ['nullable', 'string'],
            'resultats_attendus' => ['nullable', 'string'],
            'statut' => ['required', 'in:brouillon,actif,archive'],
            'version' => ['nullable', 'integer', 'min:1'],
            'description' => ['nullable', 'string'],
            'pilier_id' => ['nullable', 'exists:piliers,id'],
        ];
    }
}
