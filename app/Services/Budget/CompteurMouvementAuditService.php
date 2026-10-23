<?php

namespace App\Services\Budget;

use App\Models\Engagement;
use App\Models\LigneBudgetaire;
use App\Models\Liquidation;
use App\Models\Ordonnancement;
use App\Models\Paiement;
use App\Services\Depense\DepenseCompteurService;
use Illuminate\Support\Collection;

/**
 * Audit sémantique : reconstitue les compteurs budgétaires depuis la chaîne de la dépense
 * (engagements réservés, liquidations/ordonnancements/paiements validés) et détecte les écarts.
 */
class CompteurMouvementAuditService
{
    private const COLONNES = ['montant_engage', 'montant_liquide', 'montant_ordonnance', 'montant_paye'];

    public function __construct(private readonly DepenseCompteurService $compteurs)
    {
    }

    /** @return array<string,mixed> */
    public function analyser(?int $exerciceId = null): array
    {
        $attendu = [];
        $semantiques = [];

        $this->auditerEngagements($exerciceId, $attendu, $semantiques);
        $this->auditerEtapesAval(Liquidation::class, 'liquidation', 'montant_liquide', $exerciceId, $attendu, $semantiques);
        $this->auditerEtapesAval(Ordonnancement::class, 'ordonnancement', 'montant_ordonnance', $exerciceId, $attendu, $semantiques);
        $this->auditerEtapesAval(Paiement::class, 'paiement', 'montant_paye', $exerciceId, $attendu, $semantiques);

        $ecarts = $this->comparerAuxLignes($exerciceId, $attendu);

        return [
            'exercice_id' => $exerciceId,
            'genere_le' => now()->toIso8601String(),
            'synthese' => [
                'lignes_avec_attendu' => count($attendu),
                'ecarts_compteurs' => count($ecarts),
                'anomalies_semantiques' => count($semantiques),
            ],
            'ecarts_compteurs' => $ecarts,
            'anomalies_semantiques' => $semantiques,
            'anomalies_par_type' => collect($semantiques)->countBy('type')->merge(
                collect($ecarts)->countBy('type')
            )->all(),
            'total_anomalies' => count($ecarts) + count($semantiques),
        ];
    }

    /** @param  array<int,array<string,float>>  $attendu */
    /** @param  list<array<string,mixed>>  $semantiques */
    private function auditerEngagements(?int $exerciceId, array &$attendu, array &$semantiques): void
    {
        Engagement::query()
            ->when($exerciceId, fn ($q) => $q->where('exercice_id', $exerciceId))
            ->with('imputations')
            ->each(function (Engagement $engagement) use (&$attendu, &$semantiques) {
                if ($engagement->credits_reserves) {
                    if (in_array($engagement->statut, ['rejete', 'brouillon'], true)) {
                        $semantiques[] = $this->anomalie('CREDITS_RESERVES_INCOHERENTS', $engagement->numero, [
                            'statut' => $engagement->statut,
                        ]);
                    }

                    foreach ($engagement->imputations as $imp) {
                        $ligneId = (int) $imp->ligne_budgetaire_id;
                        $attendu[$ligneId]['montant_engage'] = ($attendu[$ligneId]['montant_engage'] ?? 0) + (float) $imp->montant;
                    }
                } elseif ($engagement->statut === 'vise') {
                    $semantiques[] = $this->anomalie('ENGAGEMENT_VISE_SANS_RESERVATION', $engagement->numero, [
                        'engagement_id' => $engagement->id,
                    ]);
                }
            });
    }

    /**
     * @param  class-string  $modele
     * @param  array<int,array<string,float>>  $attendu
     * @param  list<array<string,mixed>>  $semantiques
     */
    private function auditerEtapesAval(
        string $modele,
        string $etape,
        string $colonne,
        ?int $exerciceId,
        array &$attendu,
        array &$semantiques,
    ): void {
        $query = $modele::query();

        if ($exerciceId) {
            if ($modele === Liquidation::class) {
                $query->whereHas('engagement', fn ($q) => $q->where('exercice_id', $exerciceId));
            } elseif ($modele === Ordonnancement::class) {
                $query->whereHas('liquidation.engagement', fn ($q) => $q->where('exercice_id', $exerciceId));
            } else {
                $query->whereHas('ordonnancement.liquidation.engagement', fn ($q) => $q->where('exercice_id', $exerciceId));
            }
        }

        $with = match ($modele) {
            Liquidation::class => ['engagement.imputations'],
            Ordonnancement::class => ['liquidation.engagement.imputations'],
            Paiement::class => ['ordonnancement.liquidation.engagement.imputations'],
        };

        $query->with($with)->each(function ($document) use ($etape, $colonne, &$attendu, &$semantiques) {
            $ref = $document->numero ?? ('#'.$document->id);
            $imputations = $this->imputationsDocument($document);

            if ($document->statut === 'valide') {
                if (! $document->compteurs_appliques) {
                    $semantiques[] = $this->anomalie('ETAPE_VALIDE_SANS_COMPTEUR', $ref, [
                        'etape' => $etape,
                        'id' => $document->id,
                    ]);
                } elseif ($imputations->isNotEmpty()) {
                    $parts = $this->compteurs->repartir((float) $document->montant, $imputations);
                    foreach ($parts as $ligneId => $montant) {
                        $attendu[$ligneId][$colonne] = ($attendu[$ligneId][$colonne] ?? 0) + $montant;
                    }
                }
            } elseif ($document->compteurs_appliques) {
                $semantiques[] = $this->anomalie('COMPTEUR_APPLIQUE_SANS_VALIDATION', $ref, [
                    'etape' => $etape,
                    'statut' => $document->statut,
                    'id' => $document->id,
                ]);
            }
        });
    }

    /** @param  array<int,array<string,float>>  $attendu */
    /** @return list<array<string,mixed>> */
    private function comparerAuxLignes(?int $exerciceId, array $attendu): array
    {
        $ligneIds = array_keys($attendu);
        if ($ligneIds === []) {
            return [];
        }

        $ecarts = [];
        LigneBudgetaire::query()
            ->when($exerciceId, fn ($q) => $q->where('exercice_id', $exerciceId))
            ->whereIn('id', $ligneIds)
            ->each(function (LigneBudgetaire $ligne) use ($attendu, &$ecarts) {
                foreach (self::COLONNES as $colonne) {
                    $attenduVal = round((float) ($attendu[$ligne->id][$colonne] ?? 0), 2);
                    $reel = round((float) $ligne->{$colonne}, 2);

                    if (abs($attenduVal - $reel) > 0.05) {
                        $ecarts[] = [
                            'type' => 'ECART_COMPTEUR_LIGNE',
                            'code' => $ligne->code,
                            'ligne_id' => $ligne->id,
                            'colonne' => $colonne,
                            'attendu' => $attenduVal,
                            'reel' => $reel,
                            'ecart' => round($reel - $attenduVal, 2),
                        ];
                    }
                }
            });

        return $ecarts;
    }

    private function imputationsDocument(Liquidation|Ordonnancement|Paiement $document): Collection
    {
        return match (true) {
            $document instanceof Liquidation => $document->engagement?->imputations ?? collect(),
            $document instanceof Ordonnancement => $document->liquidation?->engagement?->imputations ?? collect(),
            $document instanceof Paiement => $document->ordonnancement?->liquidation?->engagement?->imputations ?? collect(),
        };
    }

    /** @param  array<string,mixed>  $extra */
    /** @return array<string,mixed> */
    private function anomalie(string $type, string $ref, array $extra = []): array
    {
        return array_merge(['type' => $type, 'ref' => $ref], $extra);
    }
}
