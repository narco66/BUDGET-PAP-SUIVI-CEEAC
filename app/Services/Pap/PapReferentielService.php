<?php

namespace App\Services\Pap;

use App\Models\Pap;
use App\Models\PapHistorique;
use App\Models\Pilier;
use App\Services\Budget\ExerciceService;
use App\Support\AuditLogger;
use RuntimeException;

/** Règles métier transverses PAP (verrouillage, historisation). */
class PapReferentielService
{
    public function __construct(
        private readonly PapAuditService $audit,
        private readonly PapPerformanceService $performance,
        private readonly ExerciceService $exercices,
        private readonly PapSnapshotService $snapshot,
    ) {
    }

    public function exerciceVerrouille(int $exerciceId): bool
    {
        return $this->exercices->referentielVerrouille($exerciceId);
    }

    /** Bloque la modification du référentiel RBM / budget si exercice clos ou PAP verrouillé. */
    public function assertExerciceModifiable(int $exerciceId): void
    {
        $this->exercices->assertReferentielModifiable($exerciceId);
    }

    /** Snapshot avant soumission, révision ou publication (complet à la publication). */
    public function historiser(Pap $pap, string $action, ?int $userId = null, ?string $motif = null): PapHistorique
    {
        $complet = $action === 'publication';

        $snapshot = $complet
            ? $this->snapshot->capturer($pap)
            : [
                'statut' => $pap->statut,
                'synthese' => $this->performance->executionPap($pap->exercice_id),
                'audit' => $this->audit->executer($pap->exercice_id)['anomalies_total'],
            ];

        $hist = PapHistorique::create([
            'pap_id' => $pap->id,
            'version' => $pap->version,
            'action' => $action,
            'snapshot' => $snapshot,
            'snapshot_complet' => $complet,
            'motif' => $motif,
            'user_id' => $userId,
        ]);

        AuditLogger::enregistrer("pap_{$action}", $pap, null, $snapshot);

        return $hist;
    }

    /** Rattache un pilier RBM à l'enveloppe PAP (liaison explicite). */
    public function lierPilier(Pilier $pilier, Pap $pap): void
    {
        if ((int) $pilier->pap_id === (int) $pap->id) {
            return;
        }

        $pilier->update(['pap_id' => $pap->id]);
    }

    /** Ouvre une révision post-publication (déverrouille le référentiel RBM). */
    public function demanderRevision(Pap $pap, int $userId, string $motif): PapHistorique
    {
        if (! $pap->peutDemanderRevision()) {
            throw new RuntimeException('Seul un PAP publié peut être ouvert en révision.');
        }

        $hist = $this->historiser($pap, 'ouverture_revision', $userId, $motif);
        $pap->update(['statut' => 'en_revision']);

        return $hist;
    }

    /** Rattache tous les piliers d'un exercice au PAP correspondant. */
    public function synchroniserLiensPiliers(int $exerciceId): int
    {
        $pap = Pap::where('exercice_id', $exerciceId)->first();
        if (! $pap) {
            return 0;
        }

        return Pilier::where('exercice_id', $exerciceId)
            ->where(function ($q) use ($pap) {
                $q->whereNull('pap_id')->orWhere('pap_id', '!=', $pap->id);
            })
            ->update(['pap_id' => $pap->id]);
    }

    /** Clôture automatique du PAP lors de la clôture de l'exercice (P4). */
    public function cloturerPourExercice(int $exerciceId, ?int $userId = null): int
    {
        $paps = Pap::where('exercice_id', $exerciceId)
            ->whereNotIn('statut', ['clos', 'brouillon'])
            ->get();

        foreach ($paps as $pap) {
            $pap->update(['statut' => 'clos']);
            $this->historiser($pap, 'cloture_exercice', $userId, 'Clôture automatique à la clôture de l\'exercice.');
        }

        return $paps->count();
    }
}
