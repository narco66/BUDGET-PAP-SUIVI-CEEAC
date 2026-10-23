<?php

namespace App\Http\Requests\Strategie;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProgrammationPluriannuelleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $id = $this->route('programmationPluriannuelle')?->id;

        return [
            'plan_strategique_id' => ['required', 'exists:plans_strategiques,id'],
            'reference' => ['required', 'string', 'max:40', Rule::unique('programmations_pluriannuelles', 'reference')->ignore($id)],
            'annee_debut' => ['required', 'integer', 'min:2000', 'max:2100'],
            'annee_fin' => ['required', 'integer', 'min:2000', 'max:2100', 'gte:annee_debut'],
            'statut' => ['required', 'in:brouillon,actif,archive'],
            'version' => ['nullable', 'integer', 'min:1'],
            'description' => ['nullable', 'string'],
            'scenarios' => ['nullable', 'array'],
        ];
    }
}
