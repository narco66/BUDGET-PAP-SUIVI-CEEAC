<?php

namespace App\Http\Requests;

use App\Services\Reporting\RapportPersonnaliseService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RapportPersonnaliseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $source = $this->input('source', $this->route('rapportPersonnalise')?->source);
        $champs = array_keys(RapportPersonnaliseService::SOURCES[$source]['champs'] ?? []);

        return [
            'titre' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'source' => ['required', Rule::in(array_keys(RapportPersonnaliseService::SOURCES))],
            'colonnes' => ['required', 'array', 'min:1'],
            'colonnes.*' => ['string', Rule::in($champs)],
            'partage' => ['nullable', 'boolean'],
            'actif' => ['nullable', 'boolean'],
        ];
    }
}
