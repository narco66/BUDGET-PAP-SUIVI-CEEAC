<?php

namespace Database\Seeders;

use App\Models\Budget;
use App\Models\Exercice;
use App\Models\Pap;
use App\Models\Workflow;
use Illuminate\Database\Seeder;

/**
 * Circuit de validation du PAP (CDC §6 — procédure CEEAC) :
 * Service → Direction → Département → DPPB → SG → Président → Publication.
 */
class WorkflowSeeder extends Seeder
{
    public function run(): void
    {
        $workflow = Workflow::updateOrCreate(
            ['code' => 'PAP'],
            [
                'libelle' => 'Circuit de validation du Plan Annuel de Performance',
                'type_objet' => 'pap',
                'description' => 'Validation hiérarchique du PAP jusqu’à publication.',
                'actif' => true,
            ]
        );

        $etapes = [
            [1, 'Validation Service', 'chef_service'],
            [2, 'Validation Direction', 'directeur'],
            [3, 'Validation Département', 'commissaire'],
            [4, 'Visa DPPB (contrôle budgétaire)', 'controleur_financier'],
            [5, 'Validation Secrétaire Général', 'secretaire_general'],
            [6, 'Validation Président & publication', 'president'],
        ];

        foreach ($etapes as [$ordre, $libelle, $role]) {
            $workflow->etapes()->updateOrCreate(
                ['ordre' => $ordre],
                ['libelle' => $libelle, 'role' => $role]
            );
        }

        // Circuit adoption budget (phase B1) — calqué sur le PAP
        $budgetWf = Workflow::updateOrCreate(
            ['code' => 'BUDGET'],
            [
                'libelle' => 'Circuit de validation et adoption du budget',
                'type_objet' => 'budget',
                'description' => 'Validation hiérarchique du budget institutionnel jusqu\'à adoption.',
                'actif' => true,
            ]
        );

        $etapesBudget = [
            [1, 'Validation Service', 'chef_service'],
            [2, 'Validation Direction', 'directeur'],
            [3, 'Validation Département', 'commissaire'],
            [4, 'Visa DPPB (arbitrage budgétaire)', 'controleur_financier'],
            [5, 'Validation Secrétaire Général', 'secretaire_general'],
            [6, 'Validation Président & adoption', 'president'],
        ];

        foreach ($etapesBudget as [$ordre, $libelle, $role]) {
            $budgetWf->etapes()->updateOrCreate(
                ['ordre' => $ordre],
                ['libelle' => $libelle, 'role' => $role]
            );
        }

        // Circuit Expression du besoin : validation hiérarchique (phase D1)
        $besoin = Workflow::updateOrCreate(
            ['code' => 'BESOIN'],
            ['libelle' => "Validation de l'expression du besoin", 'type_objet' => 'besoin', 'actif' => true]
        );
        $etapesBesoin = [
            [1, 'Validation Chef de service', 'chef_service'],
            [2, 'Validation Directeur', 'directeur'],
            [3, 'Visa DPPB (contrôle budgétaire)', 'controleur_financier'],
        ];
        foreach ($etapesBesoin as [$ordre, $libelle, $role]) {
            $besoin->etapes()->updateOrCreate(['ordre' => $ordre], ['libelle' => $libelle, 'role' => $role]);
        }

        // Circuit Engagement : visa du Contrôleur financier (séparation des fonctions)
        $engagement = Workflow::updateOrCreate(
            ['code' => 'ENGAGEMENT'],
            ['libelle' => "Visa de l'engagement par le contrôleur financier", 'type_objet' => 'engagement', 'actif' => true]
        );
        $engagement->etapes()->updateOrCreate(['ordre' => 1], ['libelle' => 'Visa Contrôleur financier', 'role' => 'controleur_financier']);

        // Étapes aval de la dépense (séparation ordonnateur / contrôleur / comptable)
        $circuits = [
            ['LIQUIDATION', 'Liquidation', 'liquidation', 'Visa Contrôleur financier', 'controleur_financier'],
            ['ORDONNANCEMENT', 'Ordonnancement', 'ordonnancement', 'Ordonnancement (Ordonnateur)', 'secretaire_general'],
            ['PAIEMENT', 'Paiement', 'paiement', 'Exécution du paiement (Comptable)', 'comptable'],
        ];
        foreach ($circuits as [$code, $libelle, $type, $etapeLib, $role]) {
            $wf = Workflow::updateOrCreate(
                ['code' => $code],
                ['libelle' => "Validation de la {$libelle}", 'type_objet' => $type, 'actif' => true]
            );
            $wf->etapes()->updateOrCreate(['ordre' => 1], ['libelle' => $etapeLib, 'role' => $role]);
        }

        // Circuit encaissements / recettes (P0) : Gestionnaire → CF → Comptable
        $recettes = Workflow::updateOrCreate(
            ['code' => 'RECETTES'],
            [
                'libelle' => 'Validation des encaissements de recettes',
                'type_objet' => 'encaissement',
                'description' => 'Contrôle gestionnaire, visa financier et comptabilisation.',
                'actif' => true,
            ]
        );
        $etapesRecettes = [
            [1, 'Validation gestionnaire recettes', 'gestionnaire_budgetaire'],
            [2, 'Visa contrôleur financier', 'controleur_financier'],
            [3, 'Comptabilisation comptable', 'comptable'],
        ];
        foreach ($etapesRecettes as [$ordre, $libelle, $role]) {
            $recettes->etapes()->updateOrCreate(['ordre' => $ordre], ['libelle' => $libelle, 'role' => $role]);
        }

        // Reporting MOA (P2) : validation → publication
        $rapport = Workflow::updateOrCreate(
            ['code' => 'RAPPORT'],
            [
                'libelle' => 'Validation et publication des rapports institutionnels',
                'type_objet' => 'rapport',
                'actif' => true,
            ]
        );
        foreach ([
            [1, 'Validation MOA', 'controleur_financier'],
            [2, 'Publication institutionnelle', 'secretaire_general'],
        ] as [$ordre, $libelle, $role]) {
            $rapport->etapes()->updateOrCreate(['ordre' => $ordre], ['libelle' => $libelle, 'role' => $role]);
        }

        // Plan stratégique (P2)
        $strategie = Workflow::updateOrCreate(
            ['code' => 'STRATEGIE'],
            [
                'libelle' => 'Validation du plan stratégique',
                'type_objet' => 'plan_strategique',
                'actif' => true,
            ]
        );
        foreach ([
            [1, 'Validation Direction', 'directeur'],
            [2, 'Approbation Secrétaire Général', 'secretaire_general'],
        ] as [$ordre, $libelle, $role]) {
            $strategie->etapes()->updateOrCreate(['ordre' => $ordre], ['libelle' => $libelle, 'role' => $role]);
        }

        // Collecte suivi physique (P2)
        $collecte = Workflow::updateOrCreate(
            ['code' => 'COLLECTE'],
            [
                'libelle' => 'Validation des collectes de suivi',
                'type_objet' => 'collecte',
                'actif' => true,
            ]
        );
        foreach ([
            [1, 'Vérification chef de service', 'chef_service'],
            [2, 'Validation contrôle financier', 'controleur_financier'],
        ] as [$ordre, $libelle, $role]) {
            $collecte->etapes()->updateOrCreate(['ordre' => $ordre], ['libelle' => $libelle, 'role' => $role]);
        }

        $this->definirDelaisParDefaut();

        // PAP et enveloppe budget de l'exercice 2026 (brouillon)
        $exercice = Exercice::firstWhere('annee', 2026);
        if ($exercice) {
            Pap::updateOrCreate(
                ['exercice_id' => $exercice->id],
                [
                    'reference' => 'PAP-'.$exercice->annee,
                    'libelle' => 'Plan Annuel de Performance '.$exercice->annee,
                    'statut' => 'brouillon',
                    'date_elaboration' => now()->toDateString(),
                ]
            );

            Budget::updateOrCreate(
                ['exercice_id' => $exercice->id],
                [
                    'reference' => 'BUDGET-'.$exercice->annee,
                    'libelle' => 'Budget institutionnel '.$exercice->annee,
                    'statut' => 'brouillon',
                    'date_elaboration' => now()->toDateString(),
                ]
            );
        }
    }

    /** SLA par circuit (P1) — délais institutionnels par défaut. */
    private function definirDelaisParDefaut(): void
    {
        $delais = [
            'PAP' => 5, 'BUDGET' => 5, 'BESOIN' => 3, 'ENGAGEMENT' => 2,
            'LIQUIDATION' => 2, 'ORDONNANCEMENT' => 2, 'PAIEMENT' => 2, 'RECETTES' => 3,
            'RAPPORT' => 5, 'STRATEGIE' => 7, 'COLLECTE' => 3,
        ];

        foreach ($delais as $code => $jours) {
            Workflow::where('code', $code)->first()?->etapes()->update(['delai_jours' => $jours]);
        }
    }
}
