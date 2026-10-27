<?php

namespace App\Services\Budget;

use App\Models\Activite;
use App\Models\Budget;
use App\Models\BudgetConsolidation;
use App\Models\BudgetPreparation;
use App\Models\BudgetRevision;
use App\Models\Engagement;
use App\Models\Exercice;
use App\Models\ExpressionBesoin;
use App\Models\LigneBudgetaire;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/** Diagnostic d'intégrité nomenclature / imputation / crédits (audit TB-PAPA-CEEAC). */
class BudgetAuditService
{
    public function __construct(
        private readonly ?PapBudgetRapprochementService $papBudget = null,
        private readonly ?CompteurMouvementAuditService $compteurs = null,
    ) {
    }

    /** @return array<string,mixed> */
    public function executer(?int $exerciceId = null): array
    {
        $lignes = LigneBudgetaire::query()
            ->when($exerciceId, fn ($q) => $q->where('exercice_id', $exerciceId))
            ->with('rubrique', 'activite', 'sourcesFinancement')
            ->get();

        $rapport = [
            'exercice_id' => $exerciceId,
            'genere_le' => now()->toIso8601String(),
            'nomenclature' => $this->auditerNomenclature(),
            'imputations' => $this->auditerImputations($exerciceId),
            'controle_credits' => $this->auditerControleCredits($lignes),
            'rbm_budget' => $this->auditerRbmBudget($exerciceId),
            'pap_budget' => $this->auditerPapBudget($exerciceId),
            'chaine_depense' => $this->auditerChaineDepense($exerciceId),
            'mouvements_compteurs' => $this->auditerMouvementsCompteurs($exerciceId),
            'exercices' => $this->auditerExercices($exerciceId),
            'referentiel_financier' => $this->auditerReferentielFinancier($lignes),
            'ptf' => $this->auditerPtf($exerciceId),
            'cycle_institutionnel' => $this->auditerCycleInstitutionnel($exerciceId),
        ];

        $rapport['anomalies_total'] = $this->compterAnomalies($rapport);

        return $rapport;
    }

    /** Contrôles bloquants avant soumission du budget. */
    public function peutSoumettre(int $exerciceId): bool
    {
        return $this->executer($exerciceId)['anomalies_total'] === 0;
    }

    /** @return array<string,int> */
    private function auditerNomenclature(): array
    {
        return [
            'titres' => (int) DB::table('budget_titres')->whereNull('deleted_at')->count(),
            'articles' => (int) DB::table('budget_articles')->whereNull('deleted_at')->count(),
            'paragraphes' => (int) DB::table('budget_paragraphes')->whereNull('deleted_at')->count(),
            'rubriques' => (int) DB::table('budget_rubriques')->whereNull('deleted_at')->count(),
            'rubriques_code_duplique' => (int) DB::table('budget_rubriques')
                ->select('code')
                ->whereNull('deleted_at')
                ->groupBy('code')
                ->havingRaw('COUNT(DISTINCT paragraphe_id) > 1')
                ->get()
                ->count(),
        ];
    }

    /** @return array<string,mixed> */
    private function auditerImputations(?int $exerciceId): array
    {
        $engagementsSansImputation = Engagement::query()
            ->when($exerciceId, fn ($q) => $q->where('exercice_id', $exerciceId))
            ->whereDoesntHave('imputations')
            ->count();

        $imputationsHorsExercice = (int) DB::table('engagement_imputations as ei')
            ->join('engagements as e', 'e.id', '=', 'ei.engagement_id')
            ->join('lignes_budgetaires as lb', 'lb.id', '=', 'ei.ligne_budgetaire_id')
            ->when($exerciceId, fn ($q) => $q->where('e.exercice_id', $exerciceId))
            ->whereColumn('lb.exercice_id', '!=', 'e.exercice_id')
            ->count();

        $besoinsSansLigne = ExpressionBesoin::query()
            ->when($exerciceId, fn ($q) => $q->where('exercice_id', $exerciceId))
            ->whereNotNull('activite_id')
            ->whereNull('ligne_budgetaire_id')
            ->count();

        return [
            'engagements_sans_imputation' => $engagementsSansImputation,
            'imputations_hors_exercice' => $imputationsHorsExercice,
            'besoins_activite_sans_ligne' => $besoinsSansLigne,
        ];
    }

    /** @return array<string,mixed> */
    private function auditerControleCredits(Collection $lignes): array
    {
        $anomalies = [];

        foreach ($lignes as $l) {
            $alloue = (float) ($l->montant_revise ?? $l->montant_ouvert);
            $engage = (float) $l->montant_engage;
            $liquide = (float) $l->montant_liquide;
            $ord = (float) $l->montant_ordonnance;
            $paye = (float) $l->montant_paye;

            if ($engage > $alloue + 0.01) {
                $anomalies[] = ['code' => $l->code, 'type' => 'engage_superieur_alloue'];
            }
            if ($liquide > $engage + 0.01) {
                $anomalies[] = ['code' => $l->code, 'type' => 'liquide_superieur_engage'];
            }
            if ($ord > $liquide + 0.01) {
                $anomalies[] = ['code' => $l->code, 'type' => 'ordonnance_superieur_liquide'];
            }
            if ($paye > $ord + 0.01) {
                $anomalies[] = ['code' => $l->code, 'type' => 'paye_superieur_ordonnance'];
            }
            if (! $l->actif && $engage > 0) {
                $anomalies[] = ['code' => $l->code, 'type' => 'ligne_inactive_avec_engagement'];
            }
        }

        return [
            'lignes_analysees' => $lignes->count(),
            'anomalies' => $anomalies,
            'total_anomalies' => count($anomalies),
        ];
    }

    /** @return array<string,mixed> */
    private function auditerRbmBudget(?int $exerciceId): array
    {
        $activitesAvecBudget = Activite::query()
            ->when($exerciceId, fn ($q) => $q->whereHas('sousProduit.produit.axe.pilier', fn ($p) => $p->where('exercice_id', $exerciceId)))
            ->whereNotNull('ligne_budgetaire_id')
            ->count();

        // Enveloppes PAP « ACT-{axe} » (ImputationPapService::estEnveloppePap) exclues : partagées par plusieurs
        // activités, elles n'ont pas de lien inverse unique (règle de RbmBudgetSyncService et budget:reconcilier).
        $liaisonsDesynchronisees = (int) DB::table('activites as a')
            ->join('lignes_budgetaires as lb', 'lb.id', '=', 'a.ligne_budgetaire_id')
            ->when($exerciceId, fn ($q) => $q->where('lb.exercice_id', $exerciceId))
            ->where('lb.code', 'not like', 'ACT-%')
            ->where(fn ($q) => $q->whereNull('lb.activite_id')->orWhereColumn('lb.activite_id', '!=', 'a.id'))
            ->count();

        $lignesSansActiviteAvecMontant = LigneBudgetaire::query()
            ->when($exerciceId, fn ($q) => $q->where('exercice_id', $exerciceId))
            ->whereNull('activite_id')
            ->where('code', 'not like', 'ACT-%')
            ->where('montant_ouvert', '>', 0)
            ->count();

        $ventilationDesalignee = Activite::query()
            ->when($exerciceId, fn ($q) => $q->whereHas('ligneBudgetaire', fn ($lb) => $lb->where('exercice_id', $exerciceId)))
            ->whereNotNull('ligne_budgetaire_id')
            ->where(fn ($q) => $q->where('montant_ceeac', '>', 0)->orWhere('montant_ptf', '>', 0))
            ->with(['ligneBudgetaire' => fn ($q) => $q->withSum('sourcesFinancement as total_sources', 'montant')])
            ->get()
            ->filter(function (Activite $a) {
                $ligne = $a->ligneBudgetaire;
                if (! $ligne) {
                    return false;
                }
                $pivotTotal = (float) $ligne->total_sources;
                if ($pivotTotal <= 0) {
                    return false;
                }

                return abs($pivotTotal - (float) $ligne->montant_alloue) > 0.05;
            })
            ->count();

        return [
            'activites_avec_ligne' => $activitesAvecBudget,
            'liaisons_desynchronisees' => $liaisonsDesynchronisees,
            'lignes_ouvertes_sans_activite' => $lignesSansActiviteAvecMontant,
            'ventilations_ceeac_ptf_desalignees' => $ventilationDesalignee,
        ];
    }

    /** @return array<string,mixed> */
    private function auditerPapBudget(?int $exerciceId): array
    {
        if (! $exerciceId) {
            return ['exercice_requis' => true, 'ecarts_total' => 0];
        }

        $rapport = ($this->papBudget ?? app(PapBudgetRapprochementService::class))->analyser($exerciceId);

        return [
            'synthese' => $rapport['synthese'],
            'ecarts_par_type' => $rapport['ecarts_par_type'],
            'ecarts_total' => $rapport['ecarts_total'],
        ];
    }

    /** @return array<string,mixed> */
    private function auditerMouvementsCompteurs(?int $exerciceId): array
    {
        if (! $exerciceId) {
            return ['exercice_requis' => true, 'total_anomalies' => 0];
        }

        $rapport = ($this->compteurs ?? app(CompteurMouvementAuditService::class))->analyser($exerciceId);

        return [
            'synthese' => $rapport['synthese'],
            'anomalies_par_type' => $rapport['anomalies_par_type'],
            'ecarts_compteurs' => array_slice($rapport['ecarts_compteurs'], 0, 20),
            'anomalies_semantiques' => array_slice($rapport['anomalies_semantiques'], 0, 20),
            'total_anomalies' => $rapport['total_anomalies'],
        ];
    }

    /** @return array<string,mixed> */
    private function auditerChaineDepense(?int $exerciceId): array
    {
        $engagementsVisesAvecLiquidation = (int) DB::table('engagements as e')
            ->join('liquidations as l', 'l.engagement_id', '=', 'e.id')
            ->when($exerciceId, fn ($q) => $q->where('e.exercice_id', $exerciceId))
            ->where('e.statut', 'vise')
            ->whereNotIn('l.statut', ['rejete'])
            ->distinct('e.id')
            ->count('e.id');

        return [
            'engagements_vises_avec_liquidation' => $engagementsVisesAvecLiquidation,
        ];
    }

    /** @return array<string,mixed> */
    private function auditerExercices(?int $exerciceId): array
    {
        $query = Exercice::query()->when($exerciceId, fn ($q) => $q->whereKey($exerciceId));

        $clos = (clone $query)->where('statut', 'clos')->count();
        $closAvecLignesActives = (int) DB::table('lignes_budgetaires as lb')
            ->join('exercices as e', 'e.id', '=', 'lb.exercice_id')
            ->when($exerciceId, fn ($q) => $q->where('e.id', $exerciceId))
            ->where('e.statut', 'clos')
            ->whereNull('lb.deleted_at')
            ->where('lb.montant_engage', '>', 0)
            ->distinct('e.id')
            ->count('e.id');

        return [
            'exercices_clos' => $clos,
            'exercices_clos_avec_engagements' => $closAvecLignesActives,
            'sans_exercice_actif' => Exercice::where('est_actif', true)->count() === 0 ? 1 : 0,
        ];
    }

    /** @return array<string,mixed> */
    private function auditerReferentielFinancier(Collection $lignes): array
    {
        $anomalies = [];

        foreach ($lignes as $l) {
            $pivots = $l->sourcesFinancement;
            if ($pivots->isEmpty()) {
                continue;
            }

            $alloue = (float) $l->montant_alloue;
            $somme = (float) $pivots->sum('montant');

            if (abs($somme - $alloue) > 0.05) {
                $anomalies[] = ['code' => $l->code, 'type' => 'ventilation_sources_incoherente', 'somme' => $somme, 'alloue' => $alloue];
            }

            if ($pivots->where('est_principale', true)->count() !== 1) {
                $anomalies[] = ['code' => $l->code, 'type' => 'source_principale_manquante_ou_multiple'];
            }
        }

        $sourcesExternesSansPtf = (int) DB::table('sources_financement')
            ->where('type', 'externe_ptf')
            ->whereNull('partenaire_id')
            ->where('actif', true)
            ->count();

        return [
            'lignes_multi_sources_analysees' => $lignes->filter(fn ($l) => $l->sourcesFinancement->isNotEmpty())->count(),
            'sources_externes_sans_ptf' => $sourcesExternesSansPtf,
            'anomalies' => $anomalies,
            'total_anomalies' => count($anomalies),
        ];
    }

    /** @return array<string,mixed> */
    private function auditerCycleInstitutionnel(?int $exerciceId): array
    {
        if (! $exerciceId) {
            return ['alertes' => [], 'total_alertes' => 0];
        }

        $budget = Budget::firstWhere('exercice_id', $exerciceId);
        $alertes = [];

        if (! $budget) {
            $alertes[] = ['code' => 'sans_enveloppe', 'niveau' => 'alerte'];
        } else {
            $lignes = (int) LigneBudgetaire::where('exercice_id', $exerciceId)->count();
            $preps = (int) BudgetPreparation::where('budget_id', $budget->id)->count();

            if ($lignes > 0 && $preps === 0 && $budget->estModifiable()) {
                $alertes[] = ['code' => 'preparations_non_init', 'niveau' => 'alerte'];
            }

            if ($preps > 0 && ! BudgetConsolidation::where('budget_id', $budget->id)->exists()) {
                $alertes[] = ['code' => 'sans_consolidation', 'niveau' => 'info'];
            }

            if ($budget->statut === 'adopte' && ! $budget->programmation_pluriannuelle_id) {
                $alertes[] = ['code' => 'pluri_non_rattache', 'niveau' => 'info'];
            }

            $revisionsBrouillon = (int) BudgetRevision::where('budget_id', $budget->id)->where('statut', 'brouillon')->count();
            if ($revisionsBrouillon > 0) {
                $alertes[] = ['code' => 'revisions_brouillon', 'niveau' => 'alerte', 'count' => $revisionsBrouillon];
            }
        }

        return [
            'enveloppe_statut' => $budget?->statut,
            'enveloppe_reference' => $budget?->reference,
            'preparations' => $budget ? BudgetPreparation::where('budget_id', $budget->id)->count() : 0,
            'consolidations' => $budget ? BudgetConsolidation::where('budget_id', $budget->id)->count() : 0,
            'revisions_validees' => $budget ? BudgetRevision::where('budget_id', $budget->id)->where('statut', 'valide')->count() : 0,
            'alertes' => $alertes,
            'total_alertes' => count($alertes),
        ];
    }

    /** @return array<string,mixed> */
    private function auditerPtf(?int $exerciceId): array
    {
        $conventionsSansSource = (int) DB::table('conventions_financement')
            ->whereNull('source_financement_id')
            ->where('actif', true)
            ->count();

        $conventionsExpireesActives = (int) DB::table('conventions_financement')
            ->where('statut', 'actif')
            ->whereNotNull('date_fin')
            ->whereDate('date_fin', '<', now())
            ->count();

        $partenairesSansConvention = (int) DB::table('partenaires_financiers as p')
            ->where('p.actif', true)
            ->whereNotExists(fn ($q) => $q->selectRaw('1')->from('conventions_financement as c')->whereColumn('c.partenaire_id', 'p.id'))
            ->count();

        return [
            'conventions_sans_source' => $conventionsSansSource,
            'conventions_expirees_actives' => $conventionsExpireesActives,
            'partenaires_actifs_sans_convention' => $partenairesSansConvention,
        ];
    }

    /** @param array<string,mixed> $rapport */
    private function compterAnomalies(array $rapport): int
    {
        return (int) ($rapport['controle_credits']['total_anomalies'] ?? 0)
            + (int) ($rapport['referentiel_financier']['total_anomalies'] ?? 0)
            + (int) ($rapport['imputations']['imputations_hors_exercice'] ?? 0)
            + (int) ($rapport['imputations']['engagements_sans_imputation'] ?? 0)
            + (int) ($rapport['rbm_budget']['liaisons_desynchronisees'] ?? 0)
            + (int) ($rapport['rbm_budget']['ventilations_ceeac_ptf_desalignees'] ?? 0)
            + (int) ($rapport['pap_budget']['ecarts_total'] ?? 0)
            + (int) ($rapport['mouvements_compteurs']['total_anomalies'] ?? 0);
    }
}
