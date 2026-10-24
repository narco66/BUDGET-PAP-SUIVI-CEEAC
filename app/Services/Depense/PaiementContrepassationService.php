<?php

namespace App\Services\Depense;

use App\Models\LigneBudgetaire;
use App\Models\Paiement;
use App\Models\PaiementRejet;
use App\Models\User;
use App\Support\AuditLogger;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Rejet d’instrument (virement / chèque) après visa : contrepassation auditée, pas d’effacement.
 */
class PaiementContrepassationService
{
    public function constaterRejetInstrument(Paiement $paiement, User $user, string $motif, ?string $preuve = null): PaiementRejet
    {
        if ($paiement->statut !== 'valide') {
            throw new RuntimeException('Seuls les paiements visés peuvent faire l’objet d’un rejet d’instrument.');
        }
        if ($paiement->etat_execution === 'instrument_rejete') {
            throw new RuntimeException('Ce paiement a déjà une contrepassation d’instrument.');
        }

        return DB::transaction(function () use ($paiement, $user, $motif, $preuve) {
            $verrouille = Paiement::query()->whereKey($paiement->id)->lockForUpdate()->firstOrFail();

            if ($verrouille->compteurs_appliques) {
                $this->retirerCompteurPaye($verrouille);
            }

            $verrouille->update([
                'etat_execution' => 'instrument_rejete',
                'compteurs_appliques' => false,
            ]);

            $rejet = $verrouille->rejets()->create([
                'type' => 'instrument',
                'motif' => $motif,
                'observation' => $motif,
                'preuve' => $preuve,
                'contrepassation_appliquee' => true,
                'rejete_par' => $user->id,
                'rejete_le' => now(),
            ]);

            app(HistoriqueDepenseService::class)->enregistrer($verrouille, 'paiement_instrument_rejete', $user, [
                'motif' => $motif,
            ]);
            AuditLogger::enregistrer('paiement_instrument_rejete', $verrouille, null, [
                'motif' => $motif,
                'preuve' => $preuve,
            ]);
            app(ChaineClotureService::class)->reouvrirSiNecessaire($verrouille->engagementRacine());

            return $rejet;
        });
    }

    private function retirerCompteurPaye(Paiement $paiement): void
    {
        $montant = $paiement->montantPourCompteurs();
        $imputations = $paiement->engagementRacine()->imputations()->get();
        $parts = app(DepenseCompteurService::class)->repartir($montant, $imputations);

        foreach ($parts as $ligneId => $part) {
            if ($part > 0) {
                LigneBudgetaire::whereKey($ligneId)->decrement('montant_paye', $part);
            }
        }
    }
}
