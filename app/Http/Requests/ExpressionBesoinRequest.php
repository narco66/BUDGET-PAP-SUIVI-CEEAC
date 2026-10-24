<?php

namespace App\Http\Requests;

use App\Models\Activite;
use App\Models\ExpressionBesoin;
use App\Models\ExpressionBesoinLigne;
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
        $categorie = $this->categorieEffective();

        return [
            'categorie' => ['nullable', 'in:pap,hors_pap'],
            'exercice_id' => ['required', 'exists:exercices,id'],
            'structure_id' => ['nullable', 'exists:structures,id'],
            'activite_id' => [$categorie === 'hors_pap' ? 'nullable' : 'required', 'exists:activites,id'],
            'tache_id' => ['nullable', 'exists:taches,id'],
            'ligne_budgetaire_id' => [$categorie === 'hors_pap' ? 'required' : 'nullable', 'exists:lignes_budgetaires,id'],
            'objet' => ['required', 'string', 'max:255'],
            'justification' => [$categorie === 'hors_pap' ? 'required' : 'nullable', 'string'],
            'montant_estime' => ['nullable', 'numeric', 'min:0'],
            'priorite' => ['nullable', 'in:haute,moyenne,basse'],
            'date_besoin' => ['nullable', 'date'],
            'lignes' => ['nullable', 'array'],
            'lignes.*.libelle' => ['required_with:lignes', 'string', 'max:255'],
            'lignes.*.quantite' => ['required_with:lignes', 'numeric', 'min:0.0001'],
            'lignes.*.unite' => ['nullable', 'string', 'max:30'],
            'lignes.*.prix_unitaire' => ['required_with:lignes', 'numeric', 'min:0'],
            'lignes.*.taxe' => ['nullable', 'numeric', 'min:0'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $categorie = $this->categorieEffective();
            $scopes = app(AccessScopeService::class);
            $user = $this->user();
            $existant = $this->besoinExistant();
            if ($existant && $existant->statut !== 'brouillon') {
                $origine = $existant->categorie ?? 'pap';
                if ($categorie !== $origine) {
                    $validator->errors()->add(
                        'categorie',
                        'La catégorie PAP / hors PAP ne peut plus être modifiée après la première soumission.'
                    );
                }
            }

            if ($this->input('structure_id') && ! $scopes->dansPerimetre($user, (int) $this->input('structure_id'))) {
                $validator->errors()->add('structure_id', 'Structure hors de votre perimetre institutionnel.');
            }

            if ($categorie === 'hors_pap') {
                $this->validerHorsPap($validator, $scopes, $user);

                return;
            }

            $this->validerPap($validator, $scopes, $user);
        });
    }

    /** @return list<array{ordre:int,libelle:string,quantite:float,unite:?string,prix_unitaire:float,taxe:float,montant:string}> */
    public function lignesNormalisees(): array
    {
        $lignes = $this->input('lignes', []);
        if (! is_array($lignes) || $lignes === []) {
            return [];
        }

        $normalisees = [];
        foreach (array_values($lignes) as $i => $ligne) {
            $qte = (float) ($ligne['quantite'] ?? 0);
            $pu = (float) ($ligne['prix_unitaire'] ?? 0);
            $taxe = (float) ($ligne['taxe'] ?? 0);
            $normalisees[] = [
                'ordre' => $i + 1,
                'libelle' => (string) ($ligne['libelle'] ?? ''),
                'quantite' => $qte,
                'unite' => $ligne['unite'] ?? null,
                'prix_unitaire' => $pu,
                'taxe' => $taxe,
                'montant' => ExpressionBesoinLigne::calculerMontant($qte, $pu, $taxe),
            ];
        }

        return $normalisees;
    }

    public function montantCalcule(): float
    {
        $lignes = $this->lignesNormalisees();
        if ($lignes === []) {
            return (float) $this->input('montant_estime', 0);
        }

        return array_sum(array_map(fn ($l) => (float) $l['montant'], $lignes));
    }

    private function validerPap(Validator $validator, AccessScopeService $scopes, $user): void
    {
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
    }

    private function validerHorsPap(Validator $validator, AccessScopeService $scopes, $user): void
    {
        if ($this->filled('activite_id')) {
            $validator->errors()->add('activite_id', 'Un besoin hors PAP ne doit pas hériter d’une activité PAP.');
        }

        $ligne = LigneBudgetaire::find($this->input('ligne_budgetaire_id'));
        if (! $ligne) {
            return;
        }

        if ($ligne->structure_id && ! $scopes->dansPerimetre($user, $ligne->structure_id)) {
            $validator->errors()->add('ligne_budgetaire_id', 'Ligne hors de votre perimetre institutionnel.');
        }

        if ((int) $ligne->exercice_id !== (int) $this->input('exercice_id')) {
            $validator->errors()->add('ligne_budgetaire_id', 'La ligne n’appartient pas à l’exercice sélectionné.');
        }
    }

    private function categorieEffective(): string
    {
        $saisie = $this->input('categorie');
        if ($saisie === null || $saisie === '') {
            return $this->besoinExistant()?->categorie ?? 'pap';
        }

        return (string) $saisie;
    }

    private function besoinExistant(): ?ExpressionBesoin
    {
        $besoin = $this->route('besoin');

        return $besoin instanceof ExpressionBesoin ? $besoin : null;
    }
}
