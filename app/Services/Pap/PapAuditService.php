<?php

namespace App\Services\Pap;

use App\Models\Activite;
use App\Models\Indicateur;
use App\Models\Pap;
use App\Models\Pilier;
use App\Models\Produit;
use App\Models\SousProduit;
use App\Models\Tache;
use Illuminate\Support\Facades\DB;

/** Diagnostic d'intégrité et complétude du PAP (audit TB-PAPA-CEEAC). */
class PapAuditService
{
    public function __construct(private readonly PapPerformanceService $performance)
    {
    }

    /** @return array<string,mixed> */
    public function executer(int $exerciceId): array
    {
        $anomalies = array_merge(
            $this->auditerChaine($exerciceId),
            $this->auditerIndicateurs($exerciceId),
            $this->auditerBudget($exerciceId),
            $this->auditerResponsables($exerciceId),
        );

        $critiques = collect($anomalies)->where('niveau', 'critique')->count();

        return [
            'exercice_id' => $exerciceId,
            'genere_le' => now()->toIso8601String(),
            'synthese' => $this->performance->executionPap($exerciceId),
            'consolidation_structures' => $this->performance->consolidationParStructure($exerciceId)->take(5),
            'anomalies' => $anomalies,
            'anomalies_critiques' => $critiques,
            'anomalies_total' => count($anomalies),
        ];
    }

    /** @return list<array<string,string>> */
    private function auditerChaine(int $exerciceId): array
    {
        $items = [];

        $piliersSansAxe = Pilier::where('exercice_id', $exerciceId)->whereDoesntHave('axes')->count();
        if ($piliersSansAxe) {
            $items[] = ['niveau' => 'warning', 'code' => 'PILIER_SANS_AXE', 'message' => "{$piliersSansAxe} pilier(s) sans axe."];
        }

        $produitsSansActivite = Produit::whereHas('axe.pilier', fn ($q) => $q->where('exercice_id', $exerciceId))
            ->whereDoesntHave('sousProduits.activites')->count();
        if ($produitsSansActivite) {
            $items[] = ['niveau' => 'warning', 'code' => 'PRODUIT_SANS_ACTIVITE', 'message' => "{$produitsSansActivite} produit(s) sans activité."];
        }

        $activites = Activite::whereHas('sousProduit.produit.axe.pilier', fn ($q) => $q->where('exercice_id', $exerciceId))->count();
        if ($activites === 0) {
            $items[] = ['niveau' => 'critique', 'code' => 'AUCUNE_ACTIVITE', 'message' => 'Aucune activité PAP pour cet exercice.'];
        }

        return $items;
    }

    /** @return list<array<string,string>> */
    private function auditerIndicateurs(int $exerciceId): array
    {
        $items = [];

        $activitesSansInd = Activite::whereHas('sousProduit.produit.axe.pilier', fn ($q) => $q->where('exercice_id', $exerciceId))
            ->whereDoesntHave('indicateurs')->count();
        if ($activitesSansInd) {
            $items[] = ['niveau' => 'warning', 'code' => 'ACTIVITE_SANS_INDICATEUR', 'message' => "{$activitesSansInd} activité(s) sans indicateur."];
        }

        $indSansCible = Indicateur::where('rbm_type', Activite::class)
            ->whereIn('rbm_id', Activite::whereHas('sousProduit.produit.axe.pilier', fn ($q) => $q->where('exercice_id', $exerciceId))->pluck('id'))
            ->whereNull('valeur_cible')->count();
        if ($indSansCible) {
            $items[] = ['niveau' => 'warning', 'code' => 'INDICATEUR_SANS_CIBLE', 'message' => "{$indSansCible} indicateur(s) sans cible."];
        }

        return $items;
    }

    /** @return list<array<string,string>> */
    private function auditerBudget(int $exerciceId): array
    {
        $items = [];

        $sansLigne = Activite::whereHas('sousProduit.produit.axe.pilier', fn ($q) => $q->where('exercice_id', $exerciceId))
            ->whereNull('ligne_budgetaire_id')->where('montant_total', '>', 0)->count();
        if ($sansLigne) {
            $items[] = ['niveau' => 'warning', 'code' => 'ACTIVITE_SANS_BUDGET', 'message' => "{$sansLigne} activité(s) avec montant mais sans ligne budgétaire."];
        }

        $tachesSansBudget = Tache::whereHas('activite.sousProduit.produit.axe.pilier', fn ($q) => $q->where('exercice_id', $exerciceId))
            ->where('montant', '>', 0)
            ->whereHas('activite', fn ($q) => $q->whereNull('ligne_budgetaire_id'))->count();
        if ($tachesSansBudget) {
            $items[] = ['niveau' => 'info', 'code' => 'TACHE_SANS_IMPUTATION', 'message' => "{$tachesSansBudget} tâche(s) avec montant sur activité non budgétisée."];
        }

        $desync = (int) DB::table('activites as a')
            ->join('lignes_budgetaires as lb', 'lb.id', '=', 'a.ligne_budgetaire_id')
            ->join('sous_produits as sp', 'a.sous_produit_id', '=', 'sp.id')
            ->join('produits as pr', 'sp.produit_id', '=', 'pr.id')
            ->join('axes as ax', 'pr.axe_id', '=', 'ax.id')
            ->join('piliers as p', 'ax.pilier_id', '=', 'p.id')
            ->where('p.exercice_id', $exerciceId)
            ->where('lb.code', 'not like', 'ACT-%')   // enveloppe PAP partagée : pas de lien 1:1 attendu
            ->where(function ($q) {
                $q->whereNull('lb.activite_id')->orWhereColumn('lb.activite_id', '!=', 'a.id');
            })
            ->count();
        if ($desync) {
            $items[] = ['niveau' => 'warning', 'code' => 'LIAISON_BUDGET_DESYNC', 'message' => "{$desync} liaison(s) activité↔budget désynchronisée(s). Exécutez budget:reconcilier."];
        }

        return $items;
    }

    /** @return list<array<string,string>> */
    private function auditerResponsables(int $exerciceId): array
    {
        $sansResp = Activite::whereHas('sousProduit.produit.axe.pilier', fn ($q) => $q->where('exercice_id', $exerciceId))
            ->whereNull('responsable_user_id')->count();
        if ($sansResp) {
            return [['niveau' => 'info', 'code' => 'ACTIVITE_SANS_RESPONSABLE', 'message' => "{$sansResp} activité(s) sans responsable désigné."]];
        }

        return [];
    }

    /** Contrôles bloquants avant soumission du PAP. */
    public function peutSoumettre(int $exerciceId): bool
    {
        $audit = $this->executer($exerciceId);

        return $audit['anomalies_critiques'] === 0;
    }
}
