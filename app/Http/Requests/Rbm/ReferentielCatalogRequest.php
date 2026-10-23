<?php

namespace App\Http\Requests\Rbm;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReferentielCatalogRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $table = $this->route('catalogue');
        $id = $this->route('item');
        $id = is_object($id) ? $id->id : $id;

        $tables = [
            'types-indicateurs' => 'types_indicateurs',
            'unites-mesure' => 'unites_mesure',
            'sources-verification' => 'sources_verification',
        ];

        $tableName = $tables[$table] ?? 'types_indicateurs';

        return [
            'code' => ['required', 'string', 'max:40', Rule::unique($tableName, 'code')->ignore($id)],
            'libelle' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'symbole' => ['nullable', 'string', 'max:20'],
            'url' => ['nullable', 'url', 'max:500'],
            'ordre' => ['nullable', 'integer', 'min:0'],
            'statut' => ['nullable', Rule::in(['actif', 'inactif', 'archive'])],
        ];
    }
}
