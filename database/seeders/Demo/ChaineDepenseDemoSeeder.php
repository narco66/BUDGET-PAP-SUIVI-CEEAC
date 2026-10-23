<?php

namespace Database\Seeders\Demo;

use App\Models\Exercice;
use App\Models\User;
use App\Services\Demo\DemoChaineBuilder;
use Illuminate\Database\Seeder;

/**
 * Dossiers de dépense de démonstration couvrant tous les cas métier
 * (besoin simple/urgent/rejeté, engagement simple/mission/contrat/marché/provisionnel/annulé/partiel,
 * liquidation complète/partielle, paiement total/partiel/en attente, dossier retourné, pièces justificatives).
 */
class ChaineDepenseDemoSeeder extends Seeder
{
    public function run(): void
    {
        $exercice = Exercice::firstWhere('annee', 2026);
        if (! $exercice) {
            return;
        }

        $builder = new DemoChaineBuilder($exercice);
        $gb = User::role('gestionnaire_budgetaire')->value('id');
        $cf = User::role('controleur_financier')->value('id');
        $cpt = User::role('comptable')->value('id');

        $piecesCompletes = [
            ['nom' => "Demande d'achat", 'categorie' => 'demande', 'cible' => 'besoin'],
            ['nom' => 'Note de service', 'categorie' => 'note', 'cible' => 'besoin'],
            ['nom' => 'Facture pro forma', 'categorie' => 'proforma', 'cible' => 'engagement'],
            ['nom' => 'Bon de commande', 'categorie' => 'bon_commande', 'cible' => 'engagement'],
            ['nom' => 'Facture définitive', 'categorie' => 'facture', 'cible' => 'liquidation'],
            ['nom' => 'Procès-verbal de réception', 'categorie' => 'pv_reception', 'cible' => 'liquidation'],
            ['nom' => 'Attestation de service fait', 'categorie' => 'service_fait', 'cible' => 'liquidation'],
            ['nom' => 'Ordre de paiement', 'categorie' => 'ordre_paiement', 'cible' => 'paiement'],
            ['nom' => 'Bordereau de transmission', 'categorie' => 'bordereau', 'cible' => 'paiement'],
        ];

        $dossiers = [
            // 1 — Chaîne complète : fournitures, payé en totalité
            [
                'numero' => 'ENG-2026-D001', 'objet' => 'Acquisition de fournitures de bureau', 'type' => 'bon_commande',
                'structure' => 'SVC-EXEC', 'besoin_statut' => 'valide', 'engagement_statut' => 'vise',
                'imputations' => [['ligne' => 'LB-2026-60111', 'montant' => 20_000_000]],
                'liquide' => 20_000_000, 'ordonnance' => 20_000_000, 'paye' => 20_000_000, 'paye_statut' => 'valide',
                'pieces' => $piecesCompletes,
            ],
            // 2 — Mission urgente, paiement partiel
            [
                'numero' => 'ENG-2026-D002', 'objet' => 'Mission de supervision sur le terrain', 'type' => 'mission',
                'structure' => 'DEPT-PLAN', 'besoin_statut' => 'valide', 'engagement_statut' => 'vise', 'beneficiaire' => 'Équipe de supervision',
                'imputations' => [['ligne' => 'LB-2026-6112', 'montant' => 15_000_000]],
                'liquide' => 15_000_000, 'ordonnance' => 15_000_000, 'paye' => 9_000_000, 'paye_statut' => 'valide',
                'pieces' => [
                    ['nom' => 'Ordre de mission', 'categorie' => 'ordre_mission', 'cible' => 'engagement'],
                    ['nom' => 'Rapport de mission', 'categorie' => 'rapport_mission', 'cible' => 'liquidation'],
                    ['nom' => 'Ordre de paiement', 'categorie' => 'ordre_paiement', 'cible' => 'paiement'],
                ],
            ],
            // 3 — Besoin rejeté (pas d'engagement)
            [
                'numero' => 'ENG-2026-D003', 'objet' => 'Organisation d’un séminaire (rejeté)', 'type' => 'simple',
                'structure' => 'SG', 'besoin_statut' => 'rejete',
                'imputations' => [['ligne' => 'LB-2026-617', 'montant' => 8_000_000]],
                'pieces' => [['nom' => "Demande d'achat", 'categorie' => 'demande', 'cible' => 'besoin']],
            ],
            // 4 — Marché, multi-imputations, liquidation partielle, paiement EN ATTENTE
            [
                'numero' => 'ENG-2026-D004', 'objet' => 'Acquisition d’équipements informatiques', 'type' => 'marche',
                'structure' => 'DIR-BUDGET', 'besoin_statut' => 'valide', 'engagement_statut' => 'vise', 'beneficiaire' => 'Fournisseur IT',
                'imputations' => [
                    ['ligne' => 'LB-2026-242', 'montant' => 30_000_000],
                    ['ligne' => 'LB-2026-60112', 'montant' => 10_000_000],
                ],
                'liquide' => 25_000_000, 'ordonnance' => 25_000_000, 'paye' => 25_000_000, 'paye_statut' => 'soumis', // en attente
                'pieces' => [
                    ['nom' => 'Contrat de marché', 'categorie' => 'contrat', 'cible' => 'engagement'],
                    ['nom' => 'Facture définitive', 'categorie' => 'facture', 'cible' => 'liquidation'],
                    ['nom' => 'Procès-verbal de réception', 'categorie' => 'pv_reception', 'cible' => 'liquidation'],
                ],
            ],
            // 5 — Contrat de prestation, visé sans liquidation (service fait à venir)
            [
                'numero' => 'ENG-2026-D005', 'objet' => 'Contrat de prestation de conseil', 'type' => 'convention',
                'structure' => 'DIR-BUDGET', 'besoin_statut' => 'valide', 'engagement_statut' => 'vise', 'beneficiaire' => 'Cabinet conseil',
                'imputations' => [['ligne' => 'LB-2026-613', 'montant' => 50_000_000]],
                'pieces' => [['nom' => 'Contrat de prestation', 'categorie' => 'contrat', 'cible' => 'engagement']],
            ],
            // 6 — Engagement provisionnel, soumis (en attente de visa)
            [
                'numero' => 'ENG-2026-D006', 'objet' => 'Provision charges de transferts', 'type' => 'provisionnel',
                'structure' => 'SG', 'besoin_statut' => 'valide', 'engagement_statut' => 'soumis',
                'imputations' => [['ligne' => 'LB-2026-644', 'montant' => 30_000_000]],
                'pieces' => [['nom' => 'Note de service', 'categorie' => 'note', 'cible' => 'engagement']],
            ],
            // 7 — Engagement annulé (crédits non réservés)
            [
                'numero' => 'ENG-2026-D007', 'objet' => 'Acquisition de mobilier (annulé)', 'type' => 'simple',
                'structure' => 'DIR-BUDGET', 'besoin_statut' => 'valide', 'engagement_statut' => 'annule',
                'imputations' => [['ligne' => 'LB-2026-241', 'montant' => 12_000_000]],
                'pieces' => [['nom' => "Demande d'achat", 'categorie' => 'demande', 'cible' => 'besoin']],
            ],
            // 8 — Travaux d'entretien : engagement partiellement liquidé puis payé
            [
                'numero' => 'ENG-2026-D008', 'objet' => "Travaux d'entretien du bâtiment siège", 'type' => 'simple',
                'structure' => 'DIR-INFRA', 'besoin_statut' => 'valide', 'engagement_statut' => 'vise', 'beneficiaire' => 'Entreprise BTP',
                'imputations' => [['ligne' => 'LB-2026-6141', 'montant' => 40_000_000]],
                'liquide' => 24_000_000, 'ordonnance' => 24_000_000, 'paye' => 24_000_000, 'paye_statut' => 'valide',
                'pieces' => [
                    ['nom' => 'Facture définitive', 'categorie' => 'facture', 'cible' => 'liquidation'],
                    ['nom' => 'Certificat de liquidation', 'categorie' => 'certificat_liquidation', 'cible' => 'liquidation'],
                ],
            ],
            // 9 — Carburant : chaîne complète, payé
            [
                'numero' => 'ENG-2026-D009', 'objet' => 'Achat de carburant et lubrifiant', 'type' => 'simple',
                'structure' => 'SVC-EXEC', 'besoin_statut' => 'valide', 'engagement_statut' => 'vise',
                'imputations' => [['ligne' => 'LB-2026-60113', 'montant' => 10_000_000]],
                'liquide' => 10_000_000, 'ordonnance' => 10_000_000, 'paye' => 10_000_000, 'paye_statut' => 'valide',
                'pieces' => [['nom' => 'Bon de commande', 'categorie' => 'bon_commande', 'cible' => 'engagement']],
            ],
            // 10 — Assurances : dossier retourné pour correction (engagement rejeté)
            [
                'numero' => 'ENG-2026-D010', 'objet' => 'Assurances multirisques (retourné)', 'type' => 'simple',
                'structure' => 'SG', 'besoin_statut' => 'valide', 'engagement_statut' => 'rejete',
                'imputations' => [['ligne' => 'LB-2026-615', 'montant' => 5_000_000]],
                'pieces' => [['nom' => 'Facture pro forma', 'categorie' => 'proforma', 'cible' => 'engagement']],
            ],
        ];

        foreach ($dossiers as $d) {
            $builder->construire([
                ...$d,
                'structure_id' => \App\Models\Structure::where('code', $d['structure'])->value('id'),
                'demandeur_id' => $gb,
                'controleur_id' => $cf,
                'comptable_id' => $cpt,
            ]);
        }
    }
}
