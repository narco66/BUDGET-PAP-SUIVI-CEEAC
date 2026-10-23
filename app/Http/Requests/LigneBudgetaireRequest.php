<?php

namespace App\Http\Requests;

use App\Models\LigneBudgetaire;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class LigneBudgetaireRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $id = $this->route('ligne')?->id;

        return [
            'exercice_id' => ['required', 'exists:exercices,id'],
            'structure_id' => ['nullable', 'exists:structures,id'],
            'rubrique_id' => ['required', 'exists:budget_rubriques,id'],
            'source_financement_id' => ['nullable', 'exists:sources_financement,id'],
            'sources' => ['nullable', 'array', 'max:5'],
            'sources.*.source_financement_id' => ['required_with:sources', 'exists:sources_financement,id'],
            'sources.*.montant' => ['required_with:sources', 'numeric', 'min:0'],
            'sources.*.est_principale' => ['nullable', 'boolean'],
            'activite_id' => ['nullable', 'exists:activites,id'],
            'code' => [
                'required', 'string', 'max:50',
                Rule::unique('lignes_budgetaires', 'code')
                    ->where('exercice_id', $this->input('exercice_id'))
                    ->ignore($id),
            ],
            'intitule' => ['nullable', 'string', 'max:255'],
            'montant_ouvert' => ['required', 'numeric', 'min:0'],
            'montant_revise' => ['nullable', 'numeric', 'min:0'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            $ligne = $this->route('ligne');
            if (! $ligne instanceof LigneBudgetaire) {
                return;
            }

            $alloue = (float) ($this->input('montant_revise') ?? $this->input('montant_ouvert') ?? $ligne->montant_alloue);
            if ($alloue + 0.01 < (float) $ligne->montant_engage) {
                $v->errors()->add('montant_ouvert', 'Le crédit alloué ne peut être inférieur au montant déjà engagé.');
            }
        });
    }

    protected function prepareForValidation(): void
    {
        foreach (['montant_ouvert', 'montant_revise'] as $champ) {
            if ($this->filled($champ)) {
                $this->merge([
                    $champ => (float) str_replace([' ', ' ', ','], ['', '', '.'], (string) $this->input($champ)),
                ]);
            }
        }
    }
}
