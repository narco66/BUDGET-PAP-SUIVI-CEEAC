<?php

namespace App\Http\Requests\Ged;

use Illuminate\Foundation\Http\FormRequest;

class GedDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $rules = [
            'titre' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'categorie_id' => ['nullable', 'exists:ged_categories,id'],
            'type_document_id' => ['nullable', 'exists:ged_types_documents,id'],
            'dossier_id' => ['nullable', 'exists:ged_dossiers,id'],
            'niveau_confidentialite' => ['nullable', 'string'],
            'date_document' => ['nullable', 'date'],
            'date_expiration' => ['nullable', 'date'],
            'tags' => ['nullable', 'string'],
        ];

        if ($this->isMethod('post')) {
            $rules['fichier'] = ['required', 'file', 'max:10240', 'mimes:pdf,jpg,jpeg,png,doc,docx,xls,xlsx,txt,csv'];
            $rules['module_source'] = ['nullable', 'string', 'max:50'];
        }

        return $rules;
    }

    public function attributes(): array
    {
        return [
            'categorie_id' => 'catégorie',
            'type_document_id' => 'type de document',
            'dossier_id' => 'dossier',
            'niveau_confidentialite' => 'niveau de confidentialité',
            'date_document' => 'date du document',
            'date_expiration' => 'date d\'expiration',
        ];
    }
}
