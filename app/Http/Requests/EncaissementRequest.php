<?php

namespace App\Http\Requests;

use App\Models\Encaissement;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class EncaissementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'recette_id' => ['required', 'exists:recettes,id'],
            'reference' => ['required', 'string', 'max:60', Rule::unique('encaissements', 'reference')],
            'montant' => ['required', 'numeric', 'min:0.01'],
            'devise' => ['required', 'string', 'size:3'],
            'taux_change' => ['required', 'numeric', 'min:0.000001'],
            'mode_paiement' => ['required', Rule::in(array_keys(Encaissement::MODES_PAIEMENT))],
            'banque' => ['nullable', 'string', 'max:120'],
            'reference_bancaire' => ['nullable', 'string', 'max:100'],
            'date_encaissement' => ['required', 'date'],
            'observations' => ['nullable', 'string', 'max:2000'],
            'justificatifs' => ['nullable', 'array'],
            'justificatifs.*' => ['file', 'max:10240', 'mimes:pdf,jpg,jpeg,png,doc,docx,xls,xlsx'],
        ];
    }

    public function messages(): array
    {
        return [
            'reference.unique' => 'Cette référence d\'encaissement existe déjà.',
        ];
    }
}
