<?php

namespace App\Http\Requests\Strategie;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class VisionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $id = $this->route('vision')?->id;

        return [
            'reference' => ['required', 'string', 'max:40', Rule::unique('visions', 'reference')->ignore($id)],
            'intitule' => ['required', 'string', 'max:255'],
            'periode' => ['nullable', 'string', 'max:50'],
            'date_debut' => ['nullable', 'date'],
            'date_fin' => ['nullable', 'date', 'after_or_equal:date_debut'],
            'statut' => ['required', 'in:brouillon,actif,archive,obsolete'],
            'version' => ['nullable', 'integer', 'min:1'],
            'description' => ['nullable', 'string'],
            'document_officiel' => ['nullable', 'string', 'max:500'],
        ];
    }
}
