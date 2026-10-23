<?php

namespace App\Http\Requests;

use App\Models\Marche;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MarcheRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $id = $this->route('marche')?->id;

        return [
            'numero' => ['nullable', 'string', 'max:40', Rule::unique('marches', 'numero')->ignore($id)],
            'exercice_id' => ['required', 'exists:exercices,id'],
            'expression_besoin_id' => ['nullable', 'exists:expressions_besoin,id'],
            'engagement_id' => ['nullable', 'exists:engagements,id'],
            'structure_id' => ['nullable', 'exists:structures,id'],
            'objet' => ['required', 'string', 'max:255'],
            'type_marche' => ['required', Rule::in(array_keys(Marche::TYPES))],
            'mode_passation' => ['required', Rule::in(array_keys(Marche::MODES))],
            'montant_estime' => ['required', 'numeric', 'min:0'],
            'montant_attribue' => ['nullable', 'numeric', 'min:0'],
            'attributaire' => ['nullable', 'string', 'max:255'],
            'date_lancement' => ['nullable', 'date'],
            'date_attribution' => ['nullable', 'date'],
            'statut' => ['required', Rule::in(array_keys(Marche::STATUTS))],
            'observations' => ['nullable', 'string'],
        ];
    }

    public function attributes(): array
    {
        return [
            'exercice_id' => 'exercice',
            'expression_besoin_id' => 'expression de besoin',
            'engagement_id' => 'engagement',
            'structure_id' => 'structure',
            'type_marche' => 'type de marché',
            'mode_passation' => 'mode de passation',
            'montant_estime' => 'montant estimé',
            'montant_attribue' => 'montant attribué',
        ];
    }
}
