<?php

namespace App\Http\Requests;

use App\Models\Activite;
use App\Models\LigneBudgetaire;
use App\Models\Tache;
use App\Services\AccessScopeService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class ExpressionBesoinRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'exercice_id' => ['required', 'exists:exercices,id'],
            'structure_id' => ['nullable', 'exists:structures,id'],
            'activite_id' => ['required', 'exists:activites,id'],
            'tache_id' => ['nullable', 'exists:taches,id'],
            'ligne_budgetaire_id' => ['nullable', 'exists:lignes_budgetaires,id'],
            'objet' => ['required', 'string', 'max:255'],
            'justification' => ['nullable', 'string'],
            'montant_estime' => ['required', 'numeric', 'min:0'],
            'priorite' => ['nullable', 'in:haute,moyenne,basse'],
            'date_besoin' => ['nullable', 'date'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $scopes = app(AccessScopeService::class);
            $user = $this->user();

            if ($this->input('structure_id') && ! $scopes->dansPerimetre($user, (int) $this->input('structure_id'))) {
                $validator->errors()->add('structure_id', 'Structure hors de votre perimetre institutionnel.');
            }

            $activite = Activite::find($this->input('activite_id'));
            if (! $activite) {
                return;
            }

            if ($activite->structure_id && ! $scopes->dansPerimetre($user, $activite->structure_id)) {
                $validator->errors()->add('activite_id', 'Activite hors de votre perimetre institutionnel.');
            }

            $ligneId = $this->input('ligne_budgetaire_id') ?: $activite->ligne_budgetaire_id;
            if (! $ligneId) {
                $validator->errors()->add('activite_id', "L'activite selectionnee n'est rattachee a aucune ligne budgetaire.");

                return;
            }

            $ligne = LigneBudgetaire::find($ligneId);
            if (! $ligne) {
                return;
            }

            if ((int) $ligne->exercice_id !== (int) $this->input('exercice_id')) {
                $validator->errors()->add('activite_id', "La ligne budgetaire de l'activite n'appartient pas a l'exercice selectionne.");
            }

            if ($ligne->activite_id && (int) $ligne->activite_id !== (int) $activite->id) {
                $validator->errors()->add('ligne_budgetaire_id', "La ligne budgetaire ne correspond pas a l'activite PAP.");
            }

            $tacheId = $this->input('tache_id');
            if ($tacheId) {
                $tache = Tache::find($tacheId);
                if ($tache && (int) $tache->activite_id !== (int) $activite->id) {
                    $validator->errors()->add('tache_id', "La tache selectionnee n'appartient pas a l'activite PAP.");
                }
            }
        });
    }
}
