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

    /**
     * Le champ « Activité rattachée » propose aussi les lignes du budget de fonctionnement et d'équipement
     * (valeur « L{id} ») : un tel choix constitue un besoin hors PAP imputé sur cette ligne.
     */
    protected function prepareForValidation(): void
    {
        $valeur = (string) $this->input('activite_id', '');
        if (preg_match('/^D(\d+)$/', $valeur, $m)) {
            // Ligne à 5 chiffres du budget de fonctionnement : imputation sur sa ligne budgétaire parente.
            $detail = \App\Models\LigneBudgetaireDetail::find((int) $m[1]);
            $this->merge(['categorie' => 'hors_pap', 'ligne_detail_id' => $detail?->id ?? (int) $m[1],
                'ligne_budgetaire_id' => $detail?->ligne_budgetaire_id, 'activite_id' => null, 'tache_id' => null]);
        } elseif (preg_match('/^L(\d+)$/', $valeur, $m)) {
            $this->merge(['categorie' => 'hors_pap', 'ligne_budgetaire_id' => (int) $m[1], 'ligne_detail_id' => null, 'activite_id' => null, 'tache_id' => null]);
        } elseif (ctype_digit($valeur)) {
            if (! $this->has('ligne_detail_id')) {
                $this->merge(['ligne_detail_id' => null]);
            }
            if (! $this->filled('categorie')) {
                $this->merge(['categorie' => 'pap']);
            }
            // Sans ligne transmise, l'imputation est recalculée depuis l'activité (rattachement ou enveloppe de l'axe),
            // y compris lorsqu'un besoin passe d'une ligne de fonctionnement à une activité du PAP.
            if (! $this->has('ligne_budgetaire_id')) {
                $this->merge(['ligne_budgetaire_id' => null]);
            }
        }
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
            'ligne_detail_id' => ['nullable', 'exists:lignes_budgetaires_details,id'],
            // Tâches saisies comme sous-lignes d'une ligne PAP qui n'en a pas encore.
            'taches_nouvelles' => ['nullable', 'array', 'max:50'],
            'taches_nouvelles.*.intitule' => ['required', 'string', 'max:1000'],
            'taches_nouvelles.*.montant' => ['required', 'numeric', 'min:0'],
            'taches_nouvelles.*.responsable_user_id' => ['required', 'exists:users,id'],
            // Responsable de la ligne PAP, à renseigner lorsqu'il manque dans le référentiel RBM.
            'activite_responsable_id' => ['nullable', 'exists:users,id'],
            'tache_nouvelle_concernee' => ['nullable', 'integer', 'min:0'],
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

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'activite_id.required' => 'Choisissez l\'activité du PAP ou la ligne budgétaire concernée.',
            'ligne_budgetaire_id.required' => 'Choisissez la ligne budgétaire d\'imputation.',
            'justification.required' => 'La justification est obligatoire pour un besoin imputé sur le budget de fonctionnement ou d\'équipement (hors PAP).',
            'objet.required' => 'L\'objet du besoin est obligatoire.',
            'taches_nouvelles.*.intitule.required' => 'Chaque tâche saisie doit avoir un intitulé.',
            'taches_nouvelles.*.montant.required' => 'Chaque tâche saisie doit avoir un montant.',
            'taches_nouvelles.*.responsable_user_id.required' => 'Chaque tâche saisie doit avoir un responsable.',
            'exercice_id.required' => 'Choisissez l\'exercice.',
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

        // Chaîne RBM : toute ligne PAP a un responsable ; il se renseigne ici s'il manque.
        // (Écran principal uniquement : le formulaire de la SPA ne propose pas encore ce champ.)
        if (! $this->is('api/*') && ! $activite->responsable_user_id && ! $this->filled('activite_responsable_id')) {
            $validator->errors()->add('activite_responsable_id', 'Renseignez le responsable de la ligne PAP '.\App\Services\Depense\ImputationPapService::codePap($activite).'.');
        }

        // Tâches de l'activité (une ligne PAP peut porter plusieurs activités) : leur total est égal au montant de
        // l'activité (montant estimé), et le cumul des tâches de la ligne ne dépasse pas le total de la ligne.
        $sousLignes = array_values((array) $this->input('taches_nouvelles', []));
        if ($sousLignes !== []) {
            $message = app(\App\Services\Rbm\TacheBudgetService::class)
                ->anomalieSousLignes($activite, $sousLignes, (float) $this->input('montant_estime', 0), $this->besoinExistant()?->id);
            if ($message) {
                $validator->errors()->add('taches_nouvelles', $message);
            }
            $index = $this->input('tache_nouvelle_concernee');
            if ($index !== null && $index !== '' && ! array_key_exists((int) $index, $sousLignes)) {
                $validator->errors()->add('tache_nouvelle_concernee', 'La tâche concernée doit faire partie des tâches saisies.');
            }
        }

        if ($activite->structure_id && ! $scopes->dansPerimetre($user, $activite->structure_id)) {
            $validator->errors()->add('activite_id', 'Activite hors de votre perimetre institutionnel.');
        }

        $imputation = app(\App\Services\Depense\ImputationPapService::class);
        $ligneActivite = $imputation->ligneDe($activite, (int) $this->input('exercice_id'));
        $ligneId = $this->input('ligne_budgetaire_id') ?: $ligneActivite?->id;
        if (! $ligneId) {
            $validator->errors()->add('ligne_budgetaire_id', "L'activite selectionnee n'est rattachee a aucune ligne budgetaire : choisissez la ligne budgetaire d'imputation.");

            return;
        }

        $ligne = LigneBudgetaire::find($ligneId);
        if (! $ligne) {
            return;
        }

        // Ligne choisie dans le formulaire (activité sans ligne rattachée) : elle doit relever du périmètre de l'utilisateur.
        if ($this->filled('ligne_budgetaire_id') && $ligne->structure_id && ! $scopes->dansPerimetre($user, $ligne->structure_id)) {
            $validator->errors()->add('ligne_budgetaire_id', 'Ligne hors de votre perimetre institutionnel.');
        }

        if ((int) $ligne->exercice_id !== (int) $this->input('exercice_id')) {
            $validator->errors()->add('activite_id', "La ligne budgetaire de l'activite n'appartient pas a l'exercice selectionne.");
        }

        // Volet investissement : l'enveloppe choisie doit être celle de l'axe de l'activité.
        if (\App\Services\Depense\ImputationPapService::estEnveloppePap($ligne) && $ligneActivite && (int) $ligne->id !== (int) $ligneActivite->id) {
            $validator->errors()->add('ligne_budgetaire_id', "L'enveloppe PAP choisie ne correspond pas a l'axe de l'activite.");
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
        if ($this->filled('taches_nouvelles')) {
            $validator->errors()->add('taches_nouvelles', 'Les tâches ne se saisissent que pour une ligne du PAP.');
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

        $detail = $this->filled('ligne_detail_id') ? \App\Models\LigneBudgetaireDetail::find($this->input('ligne_detail_id')) : null;
        if ($detail && (int) $detail->ligne_budgetaire_id !== (int) $ligne->id) {
            $validator->errors()->add('activite_id', 'La ligne de détail ne relève pas de la ligne budgétaire d’imputation.');
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
