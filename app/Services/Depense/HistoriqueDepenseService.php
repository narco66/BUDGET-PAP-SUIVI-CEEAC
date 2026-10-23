<?php

namespace App\Services\Depense;

use App\Models\Engagement;
use App\Models\HistoriqueDepense;
use App\Models\Liquidation;
use App\Models\Ordonnancement;
use App\Models\Paiement;
use App\Models\User;
use App\Support\CockpitCache;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/** Journal métier de la chaîne de la dépense (phase D3). */
class HistoriqueDepenseService
{
    /** @param array<string,mixed>|null $payload */
    public function enregistrer(Model $entite, string $evenement, ?User $user = null, ?array $payload = null): HistoriqueDepense
    {
        $engagement = $this->engagementRacine($entite);

        // Tout mouvement de la chaîne de dépense modifie les compteurs : invalide les agrégats de cockpit.
        CockpitCache::bump();

        return HistoriqueDepense::create([
            'engagement_id' => $engagement?->id,
            'entite_type' => $entite::class,
            'entite_id' => $entite->getKey(),
            'evenement' => $evenement,
            'user_id' => ($user ?? Auth::user())?->id,
            'payload' => $payload ?? $this->payloadParDefaut($entite),
            'created_at' => now(),
        ]);
    }

    private function engagementRacine(Model $entite): ?Engagement
    {
        return match (true) {
            $entite instanceof Engagement => $entite,
            $entite instanceof Liquidation => $entite->engagement,
            $entite instanceof Ordonnancement => $entite->liquidation?->engagement,
            $entite instanceof Paiement => $entite->ordonnancement?->liquidation?->engagement,
            default => null,
        };
    }

    /** @return array<string,mixed> */
    private function payloadParDefaut(Model $entite): array
    {
        $base = [
            'numero' => $entite->numero ?? null,
            'statut' => $entite->statut ?? null,
        ];

        if (isset($entite->montant)) {
            $base['montant'] = (float) $entite->montant;
        } elseif (isset($entite->montant_total)) {
            $base['montant'] = (float) $entite->montant_total;
        }

        return $base;
    }
}
