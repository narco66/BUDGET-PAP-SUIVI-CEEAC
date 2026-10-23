<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ExerciceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $exercice = $this->route('exercice');

        return [
            'code' => ['required', 'string', 'max:20', Rule::unique('exercices', 'code')->ignore($exercice?->id)],
            'annee' => ['required', 'integer', 'min:2000', 'max:2100', Rule::unique('exercices', 'annee')->ignore($exercice?->id)],
            'libelle' => ['required', 'string', 'max:255'],
            'date_debut' => ['required', 'date'],
            'date_fin' => ['required', 'date', 'after_or_equal:date_debut'],
            'devise' => ['required', 'string', 'size:3'],
            'taux_change_usd' => ['nullable', 'numeric', 'min:0'],
        ];
    }

    public function attributes(): array
    {
        return [
            'code' => 'code',
            'annee' => 'année',
            'libelle' => 'libellé',
            'date_debut' => 'date de début',
            'date_fin' => 'date de fin',
            'devise' => 'devise',
            'taux_change_usd' => 'taux de change USD',
        ];
    }
}
