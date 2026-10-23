<?php

namespace App\Services\Recettes;

use App\Models\AppelFonds;
use App\Models\Relance;
use App\Models\User;
use App\Services\Notifications\RecettesNotificationService;
use RuntimeException;

/** Phase R6 — émission des relances de recouvrement (manuelles et automatiques). */
class RelanceService
{
    /** Délai minimal (jours) entre deux relances automatiques d'un même appel. */
    public const DELAI_RELANCE_AUTO = 15;

    public function relancer(AppelFonds $appel, array $data, ?User $user = null, bool $automatique = false): Relance
    {
        if (in_array($appel->statut, ['solde', 'annule'], true)) {
            throw new RuntimeException('Aucune relance possible : l\'appel de fonds est soldé ou annulé.');
        }

        if ($appel->solde <= 0) {
            throw new RuntimeException('Aucune relance possible : il n\'y a aucun arriéré.');
        }

        $niveau = min((int) $appel->relances()->count() + 1, 3);

        $relance = Relance::create([
            'appel_fonds_id' => $appel->id,
            'niveau' => $data['niveau'] ?? $niveau,
            'date_relance' => $data['date_relance'] ?? now()->toDateString(),
            'canal' => $data['canal'] ?? ($automatique ? 'email' : 'courrier'),
            'destinataire' => $data['destinataire'] ?? ($appel->etatMembre?->pays ?? $appel->sourceFinancement?->libelle),
            'objet' => $data['objet'] ?? ('Relance — '.$appel->objet),
            'montant_du' => $appel->solde,
            'automatique' => $automatique,
            'observations' => $data['observations'] ?? null,
            'emise_par' => $user?->id,
        ]);

        if ($relance->canal === 'email' || $automatique) {
            app(RecettesNotificationService::class)->envoyerRelanceRecouvrement($relance);
        }

        return $relance;
    }

    /**
     * Génère automatiquement les relances pour tous les appels en retard et non soldés
     * dont la dernière relance date de plus de DELAI_RELANCE_AUTO jours (ou aucune).
     *
     * @return int nombre de relances créées
     */
    public function genererAutomatiques(?int $exerciceId = null): int
    {
        $count = 0;

        AppelFonds::query()
            ->with(['relances', 'etatMembre', 'sourceFinancement'])
            ->when($exerciceId, fn ($q) => $q->where('exercice_id', $exerciceId))
            ->whereNotIn('statut', ['solde', 'annule'])
            ->whereNotNull('date_echeance')
            ->whereDate('date_echeance', '<', now())
            ->whereColumn('montant_regle', '<', 'montant_appele')
            ->each(function (AppelFonds $appel) use (&$count) {
                $derniere = $appel->relances->first();
                if ($derniere && $derniere->date_relance->diffInDays(now()) < self::DELAI_RELANCE_AUTO) {
                    return;
                }

                $this->relancer($appel, [], null, true);
                $count++;
            });

        return $count;
    }
}
