<?php

namespace App\Http\Requests;

use App\Support\WorkflowDecisionRules;
use App\Support\WorkflowRejetCategorie;
use Illuminate\Foundation\Http\FormRequest;

class WorkflowDecisionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return WorkflowDecisionRules::regles(avecRenvoi: $this->avecRenvoi());
    }

    protected function prepareForValidation(): void
    {
        if ($this->input('decision') === 'rejeter' && ! $this->filled('categorie_rejet')) {
            $this->merge(['categorie_rejet' => WorkflowRejetCategorie::DOCUMENTAIRE]);
        }
    }

    protected function avecRenvoi(): bool
    {
        return true;
    }
}
