<?php



namespace App\Services\Depense;



use App\Models\Engagement;

use App\Models\ExpressionBesoin;

use App\Models\Liquidation;

use App\Models\LiquidationPiece;

use App\Models\OrdrePaiement;

use App\Models\Ordonnancement;

use App\Models\Paiement;

use App\Models\ServiceFait;

use Illuminate\Support\Collection;



/** Vue dossier transverse Besoin → Paiement (phase D2). */

class DepenseDossierService

{

    /** @return array<string,mixed> */

    public function depuisEngagement(Engagement $engagement): array

    {

        $engagement->load([

            'exercice', 'activite', 'tache', 'expressionBesoin.tache', 'expressionBesoin.workflowActif.etapes.acteur',

            'imputations.ligneBudgetaire', 'workflowActif.etapes.acteur', 'pieces.auteur',

            'liquidations.ordonnancements.ordresPaiement', 'liquidations.ordonnancements.paiements',

            'liquidations.servicesFaits.responsable', 'liquidations.piecesLiquidation',

            'liquidations.workflowActif.etapes.acteur', 'liquidations.pieces.auteur',

        ]);



        return $this->composer($engagement->expressionBesoin, $engagement);

    }



    /** @return array<string,mixed> */

    public function depuisBesoin(ExpressionBesoin $besoin): array

    {

        $besoin->load([

            'exercice', 'activite', 'tache', 'structure', 'demandeur', 'workflowActif.etapes.acteur', 'pieces.auteur',

            'engagements.liquidations.ordonnancements.ordresPaiement',

            'engagements.liquidations.ordonnancements.paiements',

            'engagements.liquidations.servicesFaits',

            'engagements.liquidations.piecesLiquidation',

        ]);



        $engagement = $besoin->engagements->sortByDesc('id')->first();



        return $this->composer($besoin, $engagement);

    }



    /** @return array<string,mixed> */

    private function composer(?ExpressionBesoin $besoin, ?Engagement $engagement): array

    {

        $timeline = collect();



        if ($besoin) {

            $timeline->push($this->etapeBesoin($besoin));

        }



        if ($engagement) {

            $timeline->push($this->etapeEngagement($engagement));



            foreach ($engagement->liquidations as $liq) {

                $timeline->push($this->etapeLiquidation($liq));



                foreach ($liq->ordonnancements as $ord) {

                    $timeline->push($this->etapeOrdonnancement($ord));



                    foreach ($ord->ordresPaiement as $op) {

                        $timeline->push($this->etapeOrdrePaiement($op));

                    }



                    foreach ($ord->paiements as $pay) {

                        $timeline->push($this->etapePaiement($pay));

                    }

                }

            }

        }



        return [

            'besoin' => $besoin,

            'engagement' => $engagement,

            'tache' => $engagement?->tache ?? $besoin?->tache,

            'priorite' => $besoin?->priorite,

            'timeline' => $timeline->values(),

            'pieces' => $this->piecesUnifiees($besoin, $engagement),

        ];

    }



    /** @return array<string,mixed> */

    private function etapeBesoin(ExpressionBesoin $besoin): array

    {

        return [

            'type' => 'besoin',

            'label' => 'Expression de besoin',

            'numero' => $besoin->numero,

            'statut' => $besoin->statut,

            'montant' => (float) $besoin->montant_estime,

            'date' => $besoin->date_besoin,

            'route' => route('besoins.show', $besoin),

            'workflow' => $besoin->workflowActif,

        ];

    }



    /** @return array<string,mixed> */

    private function etapeEngagement(Engagement $engagement): array

    {

        return [

            'type' => 'engagement',

            'label' => 'Engagement',

            'numero' => $engagement->numero,

            'statut' => $engagement->statut,

            'montant' => (float) $engagement->montant_total,

            'date' => $engagement->date_engagement,

            'route' => route('engagements.show', $engagement),

            'workflow' => $engagement->workflowActif,

        ];

    }



    /** @return array<string,mixed> */

    private function etapeLiquidation(Liquidation $liq): array

    {

        return [

            'type' => 'liquidation',

            'label' => 'Liquidation',

            'numero' => $liq->numero,

            'statut' => $liq->statut,

            'montant' => (float) $liq->montant,

            'date' => $liq->date_liquidation,

            'route' => route('depense.show', ['liquidation', $liq->id]),

            'workflow' => $liq->workflowActif,

            'services_faits' => $liq->servicesFaits,

            'pieces_liquidation' => $liq->piecesLiquidation,

        ];

    }



    /** @return array<string,mixed> */

    private function etapeOrdonnancement(Ordonnancement $ord): array

    {

        return [

            'type' => 'ordonnancement',

            'label' => 'Ordonnancement',

            'numero' => $ord->numero,

            'statut' => $ord->statut,

            'montant' => (float) $ord->montant,

            'date' => $ord->date_ordonnancement,

            'route' => route('depense.show', ['ordonnancement', $ord->id]),

            'workflow' => $ord->workflowActif,

        ];

    }



    /** @return array<string,mixed> */

    private function etapeOrdrePaiement(OrdrePaiement $op): array

    {

        return [

            'type' => 'ordre_paiement',

            'label' => 'Ordre de paiement',

            'numero' => $op->numero,

            'statut' => $op->statut,

            'montant' => (float) $op->montant,

            'date' => $op->date_ordre,

            'route' => null,

            'workflow' => null,

        ];

    }



    /** @return array<string,mixed> */

    private function etapePaiement(Paiement $pay): array

    {

        return [

            'type' => 'paiement',

            'label' => 'Paiement',

            'numero' => $pay->numero,

            'statut' => $pay->statut,

            'montant' => (float) $pay->montant,

            'date' => $pay->date_paiement,

            'route' => route('depense.show', ['paiement', $pay->id]),

            'workflow' => $pay->workflowActif,

        ];

    }



    /** @return Collection<int, mixed> */

    private function piecesUnifiees(?ExpressionBesoin $besoin, ?Engagement $engagement): Collection

    {

        $pieces = collect();



        if ($besoin) {

            foreach ($besoin->pieces as $p) {

                $pieces->push(['etape' => 'Besoin', 'numero' => $besoin->numero, 'piece' => $p]);

            }

        }



        if (! $engagement) {

            return $pieces;

        }



        foreach ($engagement->pieces as $p) {

            $pieces->push(['etape' => 'Engagement', 'numero' => $engagement->numero, 'piece' => $p]);

        }



        foreach ($engagement->liquidations as $liq) {

            foreach ($liq->pieces as $p) {

                $pieces->push(['etape' => 'Liquidation', 'numero' => $liq->numero, 'piece' => $p]);

            }



            foreach ($liq->piecesLiquidation as $lp) {

                $pieces->push([

                    'etape' => 'Liquidation',

                    'numero' => $liq->numero,

                    'typologie' => LiquidationPiece::TYPES[$lp->type] ?? $lp->type,

                    'reference' => $lp->reference,

                    'date' => $lp->date_piece,

                ]);

            }

        }



        return $pieces;

    }

}


