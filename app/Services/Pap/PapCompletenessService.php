<?php

namespace App\Services\Pap;

use App\Models\Activite;
use App\Models\BudgetRubrique;
use App\Models\Exercice;
use App\Models\Indicateur;
use App\Models\LigneBudgetaire;
use App\Models\Pap;
use App\Models\Pilier;
use App\Models\SourceFinancement;
use App\Models\Structure;
use App\Models\User;
use App\Services\Budget\PapBudgetRapprochementService;
use App\Services\Budget\RbmBudgetSyncService;
use App\Support\AuditLogger;
use Illuminate\Support\Collection;

/** Complétion idempotente des données PAP (phase P0 : budget, indicateurs, structures, responsables). */
class PapCompletenessService
{
    /** Codes des lignes agrégées pilier remplacées par des lignes activité. */
    private const LIGNES_AGREGATS_PAP = [
        'LB-2026-PAP1', 'LB-2026-PAP2', 'LB-2026-PAP3',
        'LB-2026-PAP4', 'LB-2026-PAP5', 'LB-2026-PAP6',
    ];

    /** Pilier RBM → structure porteuse. */
    private const PILIER_STRUCTURE = [
        '201' => 'SG',
        '202' => 'DEPT-PLAN',
        '203' => 'DEPT-PLAN',
        '204' => 'DEPT-PLAN',
        '205' => 'SG',
        '206' => 'DIR-PPB',
    ];

    public function __construct(
        private readonly RbmBudgetSyncService $budgetSync,
        private readonly PapBudgetRapprochementService $rapprochement,
        private readonly PapReferentielService $referentiel,
    ) {
    }

    /**
     * @param  array{remplacer_agregats?:bool,rapprocher?:bool,responsable_id?:int|null}  $options
     * @return array<string,int|bool>
     */
    public function completer(int $exerciceId, array $options = []): array
    {
        $exercice = Exercice::findOrFail($exerciceId);
        $pap = Pap::firstWhere('exercice_id', $exerciceId);
        $activites = $this->activitesExercice($exerciceId);

        if ($activites->isEmpty()) {
            return ['activites' => 0, 'succes' => false, 'message' => 'Aucune activité PAP pour cet exercice.'];
        }

        $stats = [
            'activites' => $activites->count(),
            'structures_assignees' => 0,
            'responsables_assignes' => 0,
            'indicateurs_crees' => 0,
            'lignes_creees' => 0,
            'agregats_supprimes' => 0,
            'liaisons_budget' => 0,
            'piliers_lies' => 0,
        ];

        $responsable = $this->resoudreResponsable($options['responsable_id'] ?? null);
        $rubrique = BudgetRubrique::firstWhere('code', '649000');
        $sourceCeeac = SourceFinancement::firstWhere('code', 'CEEAC-EM');
        $sourcePtf = SourceFinancement::firstWhere('code', 'PTF-DIVERS');

        abort_unless($rubrique && $sourceCeeac, 500, 'Référentiels budget absents (rubrique 649000 / CEEAC-EM).');

        if ($options['remplacer_agregats'] ?? true) {
            $stats['agregats_supprimes'] = $this->supprimerAgregatsPap($exerciceId);
        }

        foreach ($activites as $activite) {
            $activite->loadMissing('sousProduit.produit.axe.pilier');
            $pilierCode = $activite->sousProduit?->produit?->axe?->pilier?->code;
            $structure = $this->structurePourPilier($pilierCode);

            if ($structure && ! $activite->structure_id) {
                $activite->update(['structure_id' => $structure->id]);
                $stats['structures_assignees']++;
            }

            if ($responsable && ! $activite->responsable_user_id) {
                $activite->update(['responsable_user_id' => $responsable->id]);
                $stats['responsables_assignes']++;
            }

            if ($pap && $pilierCode) {
                $pilier = $activite->sousProduit->produit->axe->pilier;
                if (! $pilier->pap_id) {
                    $this->referentiel->lierPilier($pilier, $pap);
                    $stats['piliers_lies']++;
                }
            }

            $indCode = 'IND-'.$activite->code;
            $indicateur = Indicateur::firstOrCreate(
                ['code' => $indCode],
                [
                    'rbm_type' => Activite::class,
                    'rbm_id' => $activite->id,
                    'libelle' => 'Taux de réalisation — '.$activite->intitule,
                    'type' => 'operationnel',
                    'unite' => '%',
                    'periodicite' => 'trimestriel',
                    'valeur_reference' => 0,
                    'valeur_cible' => 100,
                    'seuil_alerte' => 50,
                ]
            );
            if ($indicateur->wasRecentlyCreated) {
                $stats['indicateurs_crees']++;
            } elseif ((int) $indicateur->rbm_id !== (int) $activite->id) {
                $indicateur->update(['rbm_id' => $activite->id, 'rbm_type' => Activite::class]);
            }

            if ((float) $activite->montant_total <= 0) {
                continue;
            }

            $ceeac = (float) $activite->montant_ceeac;
            $ptf = (float) $activite->montant_ptf;
            $montantLigne = $ceeac > 0 ? $ceeac : ($ptf > 0 ? $ptf : 0);
            $source = $ceeac > 0 ? $sourceCeeac : ($sourcePtf ?? $sourceCeeac);

            $ligne = LigneBudgetaire::updateOrCreate(
                ['exercice_id' => $exerciceId, 'code' => $activite->code],
                [
                    'rubrique_id' => $rubrique->id,
                    'structure_id' => $activite->structure_id ?? $structure?->id,
                    'source_financement_id' => $source?->id,
                    'intitule' => 'PAP — '.$activite->intitule,
                    'montant_ouvert' => $montantLigne,
                    'actif' => true,
                ]
            );

            if ($ligne->wasRecentlyCreated) {
                $stats['lignes_creees']++;
            }

            if (! $activite->ligne_budgetaire_id) {
                $activite->update(['ligne_budgetaire_id' => $ligne->id]);
                $this->budgetSync->synchroniserActivite($activite->fresh());
                $stats['liaisons_budget']++;
            } elseif ((int) $activite->ligne_budgetaire_id !== (int) $ligne->id) {
                $activite->update(['ligne_budgetaire_id' => $ligne->id]);
                $this->budgetSync->synchroniserActivite($activite->fresh());
                $stats['liaisons_budget']++;
            } elseif ((int) $ligne->activite_id !== (int) $activite->id) {
                $this->budgetSync->synchroniserActivite($activite->fresh());
                $stats['liaisons_budget']++;
            }
        }

        if ($options['rapprocher'] ?? true) {
            $rapport = $this->rapprochement->rapprocher($exerciceId, [
                'lier_codes' => true,
                'aligner_montants' => true,
            ]);
            $stats['rapprochement'] = $rapport;
        }

        AuditLogger::enregistrer('pap_completer_p0', $exercice, null, $stats);

        $audit = app(PapAuditService::class)->executer($exerciceId);
        $stats['anomalies_total'] = $audit['anomalies_total'];
        $stats['anomalies_critiques'] = $audit['anomalies_critiques'];
        $stats['succes'] = $audit['anomalies_critiques'] === 0;

        return $stats;
    }

    private function supprimerAgregatsPap(int $exerciceId): int
    {
        return LigneBudgetaire::query()
            ->where('exercice_id', $exerciceId)
            ->whereIn('code', self::LIGNES_AGREGATS_PAP)
            ->whereNull('activite_id')
            ->delete();
    }

    private function structurePourPilier(?string $pilierCode): ?Structure
    {
        $code = self::PILIER_STRUCTURE[$pilierCode ?? ''] ?? 'DEPT-PLAN';

        return Structure::firstWhere('code', $code)
            ?? Structure::firstWhere('code', 'DEPT-PLAN')
            ?? Structure::firstWhere('code', 'SG');
    }

    private function resoudreResponsable(?int $userId): ?User
    {
        if ($userId) {
            return User::find($userId);
        }

        return User::query()
            ->where('actif', true)
            ->whereHas('roles', fn ($q) => $q->whereIn('name', ['directeur', 'chef_service']))
            ->orderBy('id')
            ->first()
            ?? User::query()->where('actif', true)->orderBy('id')->first();
    }

    /** @return Collection<int,Activite> */
    private function activitesExercice(int $exerciceId): Collection
    {
        return Activite::query()
            ->with(['sousProduit.produit.axe.pilier', 'ligneBudgetaire'])
            ->whereHas('sousProduit.produit.axe.pilier', fn ($q) => $q->where('exercice_id', $exerciceId))
            ->get();
    }
}
