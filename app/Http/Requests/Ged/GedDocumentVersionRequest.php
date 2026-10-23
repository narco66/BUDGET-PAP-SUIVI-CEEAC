<?php

namespace App\Http\Requests\Ged;

use Illuminate\Foundation\Http\FormRequest;

class GedDocumentVersionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'fichier' => ['required', 'file', 'max:10240', 'mimes:pdf,jpg,jpeg,png,doc,docx,xls,xlsx,txt,csv'],
            'commentaire' => ['nullable', 'string', 'max:500'],
        ];
    }
}
