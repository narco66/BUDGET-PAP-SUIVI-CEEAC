<?php

namespace App\Http\Requests\Rbm;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MesureRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $mesure = $this->route('mesure');
        $indicateurId = $mesure?->indicateur_id ?? $this->route('indicateur')?->id;

        return [
            'periode' => [
                'required', 'string', 'max:20',
                Rule::unique('mesures', 'periode')->where('indicateur_id', $indicateurId)->ignore($mesure?->id),
            ],
            'valeur_realisee' => ['required', 'numeric'],
            'commentaire' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
