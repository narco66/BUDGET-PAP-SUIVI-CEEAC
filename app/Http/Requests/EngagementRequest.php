<?php

namespace App\Http\Requests;

use App\Models\Activite;
use App\Models\ExpressionBesoin;
use App\Models\LigneBudgetaire;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class EngagementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $besoinRules = $this->user()?->can('depenses.admin')
            ? ['nullable', 'exists:expressions_besoin,id']
            : ['required', 'exists:expressions_besoin,id'];

        return [
            'exercice_id' => ['required', 'exists:exercices,id'],
            'expression_besoin_id' => $besoinRules,
            'type' => ['required', Rule::in(['simple', 'bon_commande', 'marche', 'mission', 'subvention', 'convention', 'pluriannuel', 'provisionnel'])],
            'objet' => ['required', 'string', 'max:255'],
            'beneficiaire' => ['nullable', 'string', 'max:255'],
            'date_engagement' => ['nullable', 'date'],
            'imputations' => ['required', 'array', 'min:1'],
            'imputations.*.ligne_budgetaire_id' => ['required', 'distinct', 'exists:lignes_budgetaires,id'],
            'imputations.*.montant' => ['required', 'numeric', 'min:0.01'],
        ];
    }

    public function withValidator(Validator $v): void
    {
        $v->after(function (Validator $v) {
            $exerciceId = (int) $this->input('exercice_id');

            foreach ($this->input('imputations', []) as $i => $imp) {
                $ligne = LigneBudgetaire::find($imp['ligne_budgetaire_id'] ?? null);
                if (! $ligne) {
                    continue;
                }

                if ((int) $ligne->exercice_id !== $exerciceId) {
                    $v->errors()->add(
                        "imputations.{$i}.ligne_budgetaire_id",
                        "La ligne {$ligne->code} n'appartient pas à l'exercice de l'engagement."
                    );
                }

                if (! $ligne->estDisponible()) {
                    $v->errors()->add(
                        "imputations.{$i}.ligne_budgetaire_id",
                        "La ligne {$ligne->code} est inactive ou archivée."
                    );
                }
            }

            $besoinId = $this->input('expression_besoin_id');
            if (! $besoinId) {
                return;
            }

            $besoin = ExpressionBesoin::find($besoinId);
            if (! $besoin) {
                return;
            }

            if (! $this->user()?->can('depenses.admin') && ! in_array($besoin->statut, ['valide', 'converti'], true)) {
                $v->errors()->add('expression_besoin_id', 'Le besoin doit être validé avant engagement.');
            }

            if (! $besoin->activite_id) {
                $v->errors()->add('expression_besoin_id', 'Le besoin doit être rattaché à une activité PAP.');
            }

            if (! $besoin->ligne_budgetaire_id) {
                $v->errors()->add('expression_besoin_id', 'Le besoin doit être rattaché à une ligne budgétaire.');
            }

            if ((int) $besoin->exercice_id !== $exerciceId) {
                $v->errors()->add(
                    'expression_besoin_id',
                    "L'expression de besoin n'appartient pas à l'exercice de l'engagement."
                );

                return;
            }

            foreach ($this->input('imputations', []) as $i => $imp) {
                $ligne = LigneBudgetaire::find($imp['ligne_budgetaire_id'] ?? null);
                if (! $ligne || $this->ligneCoherenteAvecBesoin($ligne, $besoin)) {
                    continue;
                }

                $v->errors()->add(
                    "imputations.{$i}.ligne_budgetaire_id",
                    "La ligne {$ligne->code} n'est pas rattachée à l'activité du besoin."
                );
            }
        });
    }

    /** Imputation alignée sur l'activité ou la ligne portée par le besoin. */
    private function ligneCoherenteAvecBesoin(LigneBudgetaire $ligne, ExpressionBesoin $besoin): bool
    {
        if ($besoin->ligne_budgetaire_id && (int) $ligne->id === (int) $besoin->ligne_budgetaire_id) {
            return true;
        }

        if ((int) $ligne->activite_id === (int) $besoin->activite_id) {
            return true;
        }

        $ligneActivite = Activite::find($besoin->activite_id)?->ligne_budgetaire_id;

        return $ligneActivite && (int) $ligne->id === (int) $ligneActivite;
    }

    public function attributes(): array
    {
        return [
            'imputations.*.ligne_budgetaire_id' => 'ligne budgétaire',
            'imputations.*.montant' => 'montant',
            'expression_besoin_id' => 'expression de besoin',
        ];
    }
}
