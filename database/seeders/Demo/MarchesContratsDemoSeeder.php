<?php

namespace Database\Seeders\Demo;

use App\Models\Contrat;
use App\Models\Engagement;
use App\Models\Exercice;
use App\Models\Marche;
use Illuminate\Database\Seeder;

/**
 * Données de démonstration Marchés/PPM + Contrats, NON DESTRUCTIVES.
 * Rattache un marché + un contrat à chaque engagement de type « marché » existant, et ajoute
 * quelques marchés planifiés (PPM) sans exécution. Idempotent via firstOrCreate sur le numéro.
 */
class MarchesContratsDemoSeeder extends Seeder
{
    public function run(): void
    {
        $exercice = Exercice::firstWhere('annee', 2026);
        if (! $exercice) {
            return;
        }

        // 1) Un marché + contrat par engagement de type « marché »
        $engagements = Engagement::where('exercice_id', $exercice->id)->where('type', 'marche')->get();
        foreach ($engagements as $eng) {
            $suffixe = substr($eng->numero, 4); // ex. 2026-D004
            $marche = Marche::firstOrCreate(
                ['numero' => 'MAR-'.$suffixe],
                [
                    'exercice_id' => $exercice->id,
                    'expression_besoin_id' => $eng->expression_besoin_id,
                    'engagement_id' => $eng->id,
                    'objet' => $eng->objet,
                    'type_marche' => 'fournitures',
                    'mode_passation' => 'appel_offres_ouvert',
                    'montant_estime' => $eng->montant_total,
                    'montant_attribue' => $eng->montant_total,
                    'attributaire' => $eng->beneficiaire ?? 'Attributaire démonstration',
                    'date_lancement' => $exercice->date_debut ?? now(),
                    'date_attribution' => $exercice->date_debut ?? now(),
                    'statut' => 'signe',
                ]
            );

            Contrat::firstOrCreate(
                ['numero' => 'CTR-'.$suffixe],
                [
                    'exercice_id' => $exercice->id,
                    'marche_id' => $marche->id,
                    'engagement_id' => $eng->id,
                    'objet' => $eng->objet,
                    'titulaire' => $eng->beneficiaire ?? 'Titulaire démonstration',
                    'type_contrat' => 'marche',
                    'montant' => $eng->montant_total,
                    'date_signature' => $exercice->date_debut ?? now(),
                    'date_debut' => $exercice->date_debut ?? now(),
                    'date_fin' => $exercice->date_fin ?? now()->addYear(),
                    'statut' => 'en_cours',
                ]
            );
        }

        // 2) Marchés planifiés (PPM) sans exécution budgétaire
        $planifies = [
            ['MAR-2026-P001', 'Travaux de réhabilitation du siège', 'travaux', 'appel_offres_ouvert', 120_000_000, 'lance'],
            ['MAR-2026-P002', 'Acquisition de véhicules de service', 'fournitures', 'appel_offres_restreint', 85_000_000, 'en_evaluation'],
            ['MAR-2026-P003', 'Étude stratégique d’intégration régionale', 'prestations_intellectuelles', 'consultation', 45_000_000, 'planifie'],
        ];
        foreach ($planifies as [$numero, $objet, $type, $mode, $montant, $statut]) {
            Marche::firstOrCreate(
                ['numero' => $numero],
                [
                    'exercice_id' => $exercice->id,
                    'objet' => $objet,
                    'type_marche' => $type,
                    'mode_passation' => $mode,
                    'montant_estime' => $montant,
                    'montant_attribue' => 0,
                    'date_lancement' => $exercice->date_debut ?? now(),
                    'statut' => $statut,
                ]
            );
        }
    }
}
