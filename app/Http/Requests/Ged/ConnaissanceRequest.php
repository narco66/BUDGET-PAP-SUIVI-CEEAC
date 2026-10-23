<?php

namespace App\Http\Requests\Ged;

use App\Models\Ged\Connaissance;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ConnaissanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'titre' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::in(array_keys(Connaissance::TYPES))],
            'module' => ['required', Rule::in(array_keys(Connaissance::MODULES))],
            'categorie' => ['nullable', 'string', 'max:80'],
            'resume' => ['nullable', 'string', 'max:500'],
            'contenu' => ['required', 'string'],
            'tags' => ['nullable', 'string', 'max:255'],
            'ged_document_id' => ['nullable', 'exists:ged_documents,id'],
        ];
    }

    /** @return array<string, mixed> */
    public function donnees(): array
    {
        $data = $this->validated();
        if (isset($data['tags']) && is_string($data['tags'])) {
            $data['tags'] = array_values(array_filter(array_map('trim', explode(',', $data['tags']))));
        }

        return $data;
    }
}
