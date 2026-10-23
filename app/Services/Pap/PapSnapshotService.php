<?php

namespace App\Services\Pap;

use App\Models\Activite;
use App\Models\Pap;
use App\Models\PapMesureCorrective;
use App\Models\PapRisque;
use App\Models\Pilier;

/** Snapshot complet du PAP (arbre RBM, budget, risques) — phase P3. */
class PapSnapshotService
{
    public function __construct(
        private readonly PapPerformanceService $performance,
        private readonly PapAuditService $audit,
    ) {
    }

    /** @return array<string,mixed> */
    public function capturer(Pap $pap): array
    {
        $pap->load(['exercice', 'structure', 'responsable']);
        $exerciceId = $pap->exercice_id;

        return [
            'meta' => [
                'type' => 'snapshot_complet',
                'pap_id' => $pap->id,
                'reference' => $pap->reference,
                'version' => $pap->version,
                'statut' => $pap->statut,
                'exercice' => $pap->exercice?->annee,
                'capture_le' => now()->toIso8601String(),
            ],
            'enveloppe' => [
                'libelle' => $pap->libelle,
                'structure' => $pap->structure?->libelle,
                'responsable' => $pap->responsable?->name,
                'observations' => $pap->observations,
            ],
            'synthese' => $this->performance->executionPap($exerciceId),
            'audit' => $this->audit->executer($exerciceId),
            'piliers' => $this->arbreRbm($exerciceId),
            'risques' => PapRisque::where('pap_id', $pap->id)->get()->map->only([
                'code', 'libelle', 'probabilite', 'impact', 'statut', 'mitigation', 'activite_id',
            ])->all(),
            'mesures_correctives' => PapMesureCorrective::where('pap_id', $pap->id)->get()->map->only([
                'code', 'description', 'echeance', 'statut', 'resultat', 'pap_risque_id', 'activite_id',
            ])->all(),
        ];
    }

    /** @return list<array<string,mixed>> */
    private function arbreRbm(int $exerciceId): array
    {
        $piliers = Pilier::with([
            'axes.produits.sousProduits.activites.ligneBudgetaire',
            'axes.produits.sousProduits.activites.structure',
        ])->where('exercice_id', $exerciceId)->orderBy('code')->get();

        $arbre = [];
        foreach ($piliers as $pilier) {
            $noeudPilier = ['code' => $pilier->code, 'intitule' => $pilier->intitule, 'axes' => []];
            foreach ($pilier->axes as $axe) {
                $noeudAxe = ['code' => $axe->code, 'intitule' => $axe->intitule, 'produits' => []];
                foreach ($axe->produits as $produit) {
                    $noeudProd = ['code' => $produit->code, 'intitule' => $produit->intitule, 'activites' => []];
                    foreach ($produit->sousProduits as $sp) {
                        foreach ($sp->activites as $activite) {
                            $noeudProd['activites'][] = $this->noeudActivite($activite);
                        }
                    }
                    $noeudAxe['produits'][] = $noeudProd;
                }
                $noeudPilier['axes'][] = $noeudAxe;
            }
            $arbre[] = $noeudPilier;
        }

        return $arbre;
    }

    /** @return array<string,mixed> */
    private function noeudActivite(Activite $activite): array
    {
        $lb = $activite->ligneBudgetaire;

        return [
            'code' => $activite->code,
            'intitule' => $activite->intitule,
            'structure' => $activite->structure?->libelle,
            'montant_total' => (float) $activite->montant_total,
            'montant_ceeac' => (float) $activite->montant_ceeac,
            'montant_ptf' => (float) $activite->montant_ptf,
            'taux_physique' => (float) $activite->taux_realisation_physique,
            'taux_financier' => (float) $activite->taux_realisation_financier,
            'cause_ecart' => $activite->cause_ecart,
            'mesure_corrective' => $activite->mesure_corrective,
            'budget' => $lb ? [
                'code' => $lb->code,
                'montant_ouvert' => (float) $lb->montant_ouvert,
                'montant_engage' => (float) $lb->montant_engage,
                'montant_paye' => (float) $lb->montant_paye,
            ] : null,
        ];
    }
}
