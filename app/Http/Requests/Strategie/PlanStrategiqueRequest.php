<?php

namespace App\Http\Requests\Strategie;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PlanStrategiqueRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $id = $this->route('plan')?->id;

        return [
            'reference' => ['required', 'string', 'max:40', Rule::unique('plans_strategiques', 'reference')->ignore($id)],
            'intitule' => ['required', 'string', 'max:255'],
            'vision_id' => ['nullable', 'exists:visions,id'],
            'date_debut' => ['required', 'date'],
            'date_fin' => ['required', 'date', 'after_or_equal:date_debut'],
            'statut' => ['required', 'in:brouillon,actif,archive,obsolete'],
            'version' => ['nullable', 'integer', 'min:1'],
            'description' => ['nullable', 'string'],
            'document_officiel' => ['nullable', 'string', 'max:500'],
        ];
    }
}
