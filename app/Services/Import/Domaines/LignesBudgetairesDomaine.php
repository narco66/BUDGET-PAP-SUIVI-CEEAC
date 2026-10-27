<?php

namespace App\Services\Import\Domaines;

use App\Models\Budget;
use App\Models\ImportLigne;
use App\Models\LigneBudgetaire;
use App\Models\SourceFinancement;
use App\Models\Structure;
use App\Models\User;
use App\Services\Budget\NomenclatureResolver;
use App\Services\Import\Support\AnalyseLigne;
use App\Services\Import\Support\ChampImport;
use App\Services\Import\Support\ContexteImport;
use Illuminate\Database\Eloquent\Model;

/**
 * Budget annuel — lignes budgétaires d'un exercice (P1). Protection des crédits officiels :
 * - exercice clôturé ou budget de l'exercice adopté / en validation : aucune écriture (révision budgétaire) ;
 * - ligne existante : montant ouvert JAMAIS réécrit (révision ou virement) ; seuls l'intitulé et une structure
 *   ou une source encore vides peuvent être complétés en mode « mise à jour » ;
 * - ligne supprimée : jamais restaurée par import.
 */
class LignesBudgetairesDomaine implements DomaineImport
{
    public function __construct(private readonly NomenclatureResolver $resolver) {}

    public function code(): string
    {
        return 'lignes_budgetaires';
    }

    public function libelle(): string
    {
        return 'Budget annuel (lignes budgétaires)';
    }

    public function description(): string
    {
        return 'Lignes de crédits d\'un exercice : imputation, structure, source de financement et dotation initiale.';
    }

    public function champs(): array
    {
        return [
            new ChampImport('code', 'Code de la ligne', true, 'code', ['code_ligne', 'ligne']),
            new ChampImport('intitule', 'Intitulé', false, 'texte', ['libelle']),
            new ChampImport('rubrique_code', 'Code rubrique (imputation)', true, 'code', ['imputation', 'code_imputation', 'rubrique']),
            new ChampImport('paragraphe_code', 'Code paragraphe', false, 'code', ['paragraphe']),
            new ChampImport('article_code', 'Code article', false, 'code', ['article']),
            new ChampImport('titre_code', 'Code titre', false, 'code', ['titre']),
            new ChampImport('structure_code', 'Code structure responsable', false, 'code', ['structure', 'code_org', 'structure_responsable']),
            new ChampImport('source_code', 'Code source de financement', false, 'code', ['source', 'financement']),
            new ChampImport('montant_ouvert', 'Dotation initiale (XAF)', true, 'montant', ['dotation', 'montant', 'montant_initial', 'credit_ouvert']),
        ];
    }

    public function exigeExercice(): bool
    {
        return true;
    }

    public function modes(): array
    {
        return ['insertion', 'mise_a_jour', 'simulation'];
    }

    public function circuit(): ?string
    {
        return 'IMPORT_BUDGET';
    }

    public function autorise(User $user): bool
    {
        return $user->can('ligne_budgetaire.creer');
    }

    public function exemple(): array
    {
        return [
            ['code' => 'LB-2027-6143', 'intitule' => 'Maintenance et réparation', 'rubrique_code' => '614300', 'paragraphe_code' => '614',
                'article_code' => '61', 'titre_code' => '6', 'structure_code' => 'DIR-BUDGET', 'source_code' => 'BUDGET-CEEAC', 'montant_ouvert' => '7500000'],
        ];
    }

    public function preparer(ContexteImport $ctx): void
    {
        $exercice = $ctx->exercice;
        $ctx->cache['structures'] = Structure::query()->pluck('id', 'code');
        $ctx->cache['sources'] = SourceFinancement::query()->pluck('id', 'code');
        $ctx->cache['lignes'] = $exercice
            ? LigneBudgetaire::withTrashed()->where('exercice_id', $exercice->id)->get()->keyBy('code')
            : collect();
        $budget = $exercice ? Budget::query()->where('exercice_id', $exercice->id)->latest('id')->first() : null;
        $ctx->cache['verrou'] = match (true) {
            ! $exercice => 'Exercice non précisé.',
            $exercice->statut === 'clos' => "Exercice {$exercice->annee} clôturé : aucune écriture budgétaire n'est autorisée.",
            in_array((int) $exercice->annee, config('import.exercices_proteges', []), true)
                => "Budget {$exercice->annee} protégé : aucune ligne n'est créée par import (procédure budgétaire dédiée).",
            $budget && in_array($budget->statut, ['adopte', 'en_validation', 'valide'], true)
                => "Budget {$exercice->annee} {$budget->statut} : toute nouvelle ligne passe par une révision budgétaire, pas par un import.",
            default => null,
        };
    }

    public function prevoir(array $lignes, ContexteImport $ctx): void
    {
    }

    public function analyser(AnalyseLigne $ligne, int $numero, ContexteImport $ctx): void
    {
        $d = $ligne->donnees;
        $ligne->cle = ($ctx->exercice?->id ?? '?').'|'.($d['code'] ?? '');

        $codes = array_intersect_key($d, array_flip(['rubrique_code', 'paragraphe_code', 'article_code', 'titre_code']));
        $rubrique = $this->resolver->resoudreRubrique($codes);
        if (! $rubrique) {
            $ligne->bloquante('reference_inexistante', $this->resolver->messageErreurResolution($codes), 'rubrique_code', $d['rubrique_code'] ?? null, 'Importer ou corriger d\'abord la nomenclature budgétaire.');
        }
        $ligne->donnees['rubrique_id'] = $rubrique?->id;
        $ligne->donnees['intitule'] ??= $rubrique?->intitule;

        foreach (['structure_code' => ['structures', 'structure_id', 'Structure'], 'source_code' => ['sources', 'source_financement_id', 'Source de financement']] as $champ => [$cache, $cible, $libelle]) {
            $ligne->donnees[$cible] = null;
            if (! empty($d[$champ])) {
                $id = $ctx->cache[$cache][$d[$champ]] ?? null;
                $id ? $ligne->donnees[$cible] = $id : $ligne->bloquante('reference_inexistante', "{$libelle} « {$d[$champ]} » introuvable.", $champ, $d[$champ]);
            } elseif ($champ === 'structure_code') {
                $ligne->avertissement('valeur_manquante', 'Ligne sans structure responsable.', $champ);
            }
        }

        if (isset($d['montant_ouvert']) && (float) $d['montant_ouvert'] < 0) {
            $ligne->bloquante('montant_negatif', 'La dotation ne peut pas être négative.', 'montant_ouvert', $d['montant_ouvert']);
        }

        /** @var LigneBudgetaire|null $existante */
        $existante = $ctx->cache['lignes'][$d['code'] ?? ''] ?? null;
        if (! $existante) {
            if ($ctx->cache['verrou']) {
                $ligne->bloquante('budget_protege', $ctx->cache['verrou'], 'code', $d['code'] ?? null);
            }
            $ligne->operation = 'inserer';

            return;
        }

        $ligne->cible = $existante;
        if ($existante->trashed()) {
            $ligne->majeure('ligne_supprimee', "La ligne {$existante->code} a été supprimée : elle n'est pas restaurée par import.", 'code', $existante->code, 'Restauration par la procédure budgétaire dédiée.');
            $ligne->operation = 'conflit';

            return;
        }
        if (isset($d['montant_ouvert']) && round((float) $d['montant_ouvert'], 2) !== round((float) $existante->montant_ouvert, 2)) {
            $ligne->majeure('credit_protege', 'Dotation différente de la base ('.number_format((float) $existante->montant_ouvert, 0, ',', ' ').' XAF) : un import ne modifie pas un crédit ouvert.', 'montant_ouvert', $d['montant_ouvert'], 'Passer par une révision budgétaire ou un virement de crédits.');
        }
        if ($rubrique && (int) $existante->rubrique_id !== (int) $rubrique->id) {
            $ligne->majeure('modification_protegee', 'Imputation différente de la base : non modifiée par import.', 'rubrique_code', $d['rubrique_code'] ?? null);
        }
        if ($ligne->donnees['intitule'] !== null && $ligne->donnees['intitule'] !== $existante->intitule) {
            $ligne->difference('intitule', $existante->intitule, $ligne->donnees['intitule']);
        }
        foreach (['structure_id', 'source_financement_id'] as $champ) {
            $nouveau = $ligne->donnees[$champ];
            if ($nouveau && ! $existante->{$champ}) {
                $ligne->difference($champ, null, $nouveau);   // complément d'une donnée vide : autorisé
            } elseif ($nouveau && (int) $existante->{$champ} !== (int) $nouveau) {
                $ligne->majeure('modification_protegee', ($champ === 'structure_id' ? 'Structure' : 'Source').' différente de la base : non modifiée par import.', $champ === 'structure_id' ? 'structure_code' : 'source_code');
            }
        }

        if ($ligne->aDesMajeures()) {
            $ligne->operation = 'conflit';
        } elseif ($ligne->differences === []) {
            $ligne->operation = 'identique';
        } elseif ($ctx->miseAJourAutorisee()) {
            $ligne->operation = 'mettre_a_jour';
        } else {
            $ligne->operation = 'ignorer';
            $ligne->avertissement('existe_deja', 'Ligne existante : compléments non appliqués en mode « insertion ».', 'code', $existante->code);
        }
    }

    public function rangExecution(ImportLigne $ligne): int
    {
        return 1;
    }

    public function executer(ImportLigne $ligne, ContexteImport $ctx): ?Model
    {
        $d = $ligne->donnees;
        if ($ligne->operation === 'mettre_a_jour') {
            $existante = LigneBudgetaire::query()->findOrFail($ligne->cible_id);
            $existante->update(array_intersect_key(array_map(fn ($v) => $v['apres'], $ligne->differences ?? []), array_flip(['intitule', 'structure_id', 'source_financement_id'])));

            return $existante;
        }

        return LigneBudgetaire::create([
            'exercice_id' => $ctx->exercice->id,
            'code' => $d['code'],
            'intitule' => $d['intitule'],
            'rubrique_id' => $d['rubrique_id'],
            'structure_id' => $d['structure_id'],
            'source_financement_id' => $d['source_financement_id'],
            'montant_ouvert' => $d['montant_ouvert'],
        ]);
    }
}
