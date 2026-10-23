<?php



namespace App\Http\Requests\Rbm;



use Illuminate\Foundation\Http\FormRequest;

use Illuminate\Validation\Rule;



class IndicateurRequest extends FormRequest

{

    public function authorize(): bool

    {

        return true;

    }



    public function rules(): array

    {

        $indicateur = $this->route('indicateur');



        return [

            'code' => ['required', 'string', 'max:40', Rule::unique('indicateurs', 'code')->ignore($indicateur?->id)],

            'libelle' => ['required', 'string', 'max:255'],

            'type_indicateur_id' => ['required', 'exists:types_indicateurs,id'],

            'unite_mesure_id' => ['required', 'exists:unites_mesure,id'],

            'type' => ['prohibited'],

            'unite' => ['prohibited'],

            'methode_calcul' => ['nullable', 'string'],

            'source_verification' => ['nullable', 'string', 'max:255'],

            'sources_verification' => ['nullable', 'array'],

            'sources_verification.*' => ['exists:sources_verification,id'],

            'periodicite' => ['required', Rule::in(['mensuel', 'trimestriel', 'semestriel', 'annuel', 'pluriannuel'])],

            'valeur_reference' => ['nullable', 'numeric'],

            'valeur_cible' => ['nullable', 'numeric'],

            'seuil_alerte' => ['nullable', 'numeric'],

            'statut' => ['nullable', Rule::in(['actif', 'inactif', 'archive'])],

        ];

    }



    public function attributes(): array

    {

        return [

            'type_indicateur_id' => 'type d\'indicateur',

            'unite_mesure_id' => 'unité de mesure',

        ];

    }

}

