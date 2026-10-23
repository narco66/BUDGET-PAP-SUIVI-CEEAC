<?php

namespace App\Http\Requests\Strategie;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PortefeuilleProjetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $id = $this->route('portefeuille')?->id;

        return [
            'reference' => ['required', 'string', 'max:40', Rule::unique('portefeuilles_projets', 'reference')->ignore($id)],
            'intitule' => ['required', 'string', 'max:255'],
            'exercice_id' => ['nullable', 'exists:exercices,id'],
            'responsable_user_id' => ['nullable', 'exists:users,id'],
            'statut' => ['required', 'in:actif,archive'],
            'description' => ['nullable', 'string'],
            'projet_ids' => ['nullable', 'array'],
            'projet_ids.*' => ['integer', 'exists:projets,id'],
        ];
    }
}
