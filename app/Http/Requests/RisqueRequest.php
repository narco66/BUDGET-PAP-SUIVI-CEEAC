<?php

namespace App\Http\Requests;

use App\Models\Risque;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RisqueRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // autorisation gérée dans le contrôleur (risques.creer / risques.modifier)
    }

    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:40', Rule::unique('risques', 'code')->ignore($this->route('risque'))],
            'categorie_risque_id' => ['nullable', Rule::exists('categories_risques', 'id')],
            'structure_id' => ['nullable', Rule::exists('structures', 'id')],
            'exercice_id' => ['nullable', Rule::exists('exercices', 'id')],
            'proprietaire_user_id' => ['nullable', Rule::exists('users', 'id')],
            'libelle' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'processus' => ['nullable', 'string', 'max:120'],
            'probabilite' => ['required', 'integer', 'between:1,4'],
            'impact' => ['required', 'integer', 'between:1,4'],
            'mesures_maitrise' => ['nullable', 'string'],
            'statut' => ['required', Rule::in(array_keys(Risque::STATUTS))],
        ];
    }
}
