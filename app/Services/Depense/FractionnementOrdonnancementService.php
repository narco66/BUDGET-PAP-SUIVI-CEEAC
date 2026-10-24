<?php

namespace App\Services\Depense;

use App\Models\Ordonnancement;
use App\Support\AuditLogger;
use App\Support\Montant;

/** Alerte d’audit : fractionnement apparent pour rester sous le seuil. Ne bloque pas (assiette juridique non validée). */
class FractionnementOrdonnancementService
{
    public function __construct(private readonly SeuilOrdonnancementService $seuils) {}

    /** @return array<string, mixed>|null */
    public function alerte(?Ordonnancement $ordonnancement): ?array
    {
        if (! $ordonnancement?->liquidation?->engagement_id) {
            $ordonnancement?->load('liquidation.engagement');
        }
        $engagementId = $ordonnancement?->liquidation?->engagement_id;
        if (! $engagementId) {
            return null;
        }

        $seuil = (float) $this->seuils->snapshotPour((float) $ordonnancement->montant)['montant'];
        $ords = Ordonnancement::query()
            ->whereHas('liquidation', fn ($q) => $q->where('engagement_id', $engagementId))
            ->whereNotIn('statut', ['rejete', 'annule'])
            ->get();

        if ($ords->count() < 2) {
            return null;
        }

        $cumul = (float) $ords->sum('montant');
        $tousSousSeuil = $ords->every(fn (Ordonnancement $o) => (float) $o->montant <= $seuil + 0.005);

        if (! $tousSousSeuil || $cumul <= $seuil + 0.005) {
            return null;
        }

        return [
            'message' => 'Alerte d’audit : plusieurs ordonnancements inférieurs au seuil sur le même engagement, dont le cumul dépasse le seuil. Vérifier l’absence de fractionnement artificiel.',
            'seuil' => Montant::api($seuil),
            'cumul' => Montant::api($cumul),
            'nombre' => $ords->count(),
        ];
    }

    public function journaliser(?Ordonnancement $ordonnancement): void
    {
        $alerte = $this->alerte($ordonnancement);
        if (! $alerte || ! $ordonnancement) {
            return;
        }

        AuditLogger::enregistrer('ordonnancement_alerte_fractionnement', $ordonnancement, null, $alerte);
    }
}
