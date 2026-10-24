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

        $roleDrhmg = (string) config('chaine_depense.role_drhmg', 'directeur');

        $besoinPap = Workflow::updateOrCreate(
            ['code' => 'BESOIN_PAP'],
            ['libelle' => 'EB PAP — Directeur puis Commissaire', 'type_objet' => 'besoin', 'actif' => true]
        );
        foreach ([
            [1, 'Validation Directeur', 'directeur'],
            [2, 'Validation Commissaire (département technique)', 'commissaire'],
        ] as [$ordre, $libelle, $role]) {
            $besoinPap->etapes()->updateOrCreate(['ordre' => $ordre], ['libelle' => $libelle, 'role' => $role]);
        }

        $besoinPapAppui = Workflow::updateOrCreate(
            ['code' => 'BESOIN_PAP_APPUI'],
            ['libelle' => 'EB PAP — Directeur puis Secrétaire général (directions d’appui)', 'type_objet' => 'besoin', 'actif' => true]
        );
        foreach ([
            [1, 'Validation Directeur', 'directeur'],
            [2, 'Validation Secrétaire général', 'secretaire_general'],
        ] as [$ordre, $libelle, $role]) {
            $besoinPapAppui->etapes()->updateOrCreate(['ordre' => $ordre], ['libelle' => $libelle, 'role' => $role]);
        }

        $besoinHors = Workflow::updateOrCreate(
            ['code' => 'BESOIN_HORS_PAP'],
            ['libelle' => 'EB hors PAP — DRHMG, SG, Ordonnateur (Président)', 'type_objet' => 'besoin', 'actif' => true]
        );
        foreach ([
            [1, 'Validation DRHMG', $roleDrhmg],
            [2, 'Validation Secrétaire général', 'secretaire_general'],
            [3, 'Approbation Ordonnateur (Président)', 'president'],
        ] as [$ordre, $libelle, $role]) {
            $besoinHors->etapes()->updateOrCreate(['ordre' => $ordre], ['libelle' => $libelle, 'role' => $role]);
        }

        // Circuit Engagement : visa du Contrôleur financier (séparation des fonctions)
        $engagement = Workflow::updateOrCreate(
            ['code' => 'ENGAGEMENT'],
            ['libelle' => "Visa de l'engagement par le contrôleur financier", 'type_objet' => 'engagement', 'actif' => true]
        );
        $roleDirBudget = (string) config('chaine_depense.role_directeur_budget', 'directeur');
        $engagement->etapes()->updateOrCreate(['ordre' => 1], [
            'libelle' => 'Validation Direction du Budget',
            'role' => $roleDirBudget,
        ]);
        $engagement->etapes()->updateOrCreate(['ordre' => 2], [
            'libelle' => 'Visa Contrôleur financier',
            'role' => 'controleur_financier',
        ]);

        $liq = Workflow::updateOrCreate(
            ['code' => 'LIQUIDATION'],
            ['libelle' => 'Validation de la Liquidation', 'type_objet' => 'liquidation', 'actif' => true]
        );
        $liq->etapes()->updateOrCreate(['ordre' => 1], [
            'libelle' => 'Certification du service fait (service initiateur)',
            'role' => 'chef_service',
        ]);
        $liq->etapes()->updateOrCreate(['ordre' => 2], [
            'libelle' => 'Visa Contrôleur financier',
            'role' => 'controleur_financier',
        ]);

        $ordPresident = Workflow::updateOrCreate(
            ['code' => 'ORDONNANCEMENT_PRESIDENT'],
            ['libelle' => 'Ordonnancement (ordonnateur principal — Président)', 'type_objet' => 'ordonnancement', 'actif' => true]
        );
        $ordPresident->etapes()->updateOrCreate(['ordre' => 1], [
            'libelle' => 'Signature du Président de la Commission',
            'role' => 'president',
        ]);

        $roleAgent = (string) config('chaine_depense.role_agent_comptable', 'agent_comptable');
        $pay = Workflow::updateOrCreate(
            ['code' => 'PAIEMENT'],
            ['libelle' => 'Validation du Paiement', 'type_objet' => 'paiement', 'actif' => true]
        );
        $pay->etapes()->updateOrCreate(['ordre' => 1], [
            'libelle' => 'Contrôles comptables',
            'role' => 'comptable',
        ]);
        $pay->etapes()->updateOrCreate(['ordre' => 2], [
            'libelle' => 'Autorisation de décaissement (Agent comptable)',
            'role' => $roleAgent,
        ]);

        $circuits = [
            ['ORDONNANCEMENT', 'Ordonnancement', 'ordonnancement', 'Ordonnancement (Secrétaire général)', 'secretaire_general'],
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
            'PAP' => 5, 'BUDGET' => 5, 'BESOIN' => 3, 'BESOIN_PAP' => 3, 'BESOIN_PAP_APPUI' => 3, 'BESOIN_HORS_PAP' => 3, 'ENGAGEMENT' => 2,
            'LIQUIDATION' => 2, 'ORDONNANCEMENT' => 2, 'ORDONNANCEMENT_PRESIDENT' => 2, 'PAIEMENT' => 2, 'RECETTES' => 3,
            'RAPPORT' => 5, 'STRATEGIE' => 7, 'COLLECTE' => 3,
        ];

        foreach ($delais as $code => $jours) {
            Workflow::where('code', $code)->first()?->etapes()->update(['delai_jours' => $jours]);
        }
    }
}
