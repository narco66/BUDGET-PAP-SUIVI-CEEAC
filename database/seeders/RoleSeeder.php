<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Permissions (module × action, avec métadonnées pour la matrice) + 13 rôles par défaut
 * typés (système/métier) et protégés (rôles sensibles non supprimables / non auto-attribuables).
 */
class RoleSeeder extends Seeder
{
    /** @var array<string,string> */
    private array $modules = [
        'structure' => 'Référentiel institutionnel',
        'administratif' => 'Référentiel administratif',
        'exercice' => 'Exercices budgétaires',
        'source_financement' => 'Sources de financement',
        'partenaire_financier' => 'Partenaires financiers (PTF)',
        'convention_financement' => 'Conventions de financement',
        'utilisateur' => 'Utilisateurs',
        'role' => 'Rôles',
        'profil' => 'Profils',
        'rbm' => 'GAR / RBM',
        'pap' => 'Plan Annuel de Performance',
        'nomenclature' => 'Nomenclature budgétaire',
        'ligne_budgetaire' => 'Lignes budgétaires',
        'structure_budget' => 'Structure du budget',
        'budget' => 'Enveloppe budgétaire (adoption)',
        'besoin' => 'Expression des besoins',
        'marche' => 'Marchés publics',
        'contrat' => 'Contrats',
        'engagement' => 'Engagement',
        'liquidation' => 'Liquidation',
        'ordonnancement' => 'Ordonnancement',
        'paiement' => 'Paiement',
        'indicateur' => 'Indicateurs',
        'suivi' => 'Suivi-évaluation',
        'recommandation' => 'Recommandations',
        'formation' => 'Formations & ateliers',
        'immobilisation' => 'Immobilisations & inventaire',
        'ged' => 'Gestion documentaire',
        'rapport' => 'Rapports',
        'tableau_bord' => 'Tableaux de bord',
        'audit' => 'Audit',
        'risques' => 'Gestion des risques',
        'controle_interne' => 'Contrôle interne (COSO)',
        'conformite' => 'Conformité',
        'incident' => 'Gestion des incidents',
        'parametre' => 'Paramètres',
    ];

    /** @var array<string,string> */
    private array $actions = [
        'voir' => 'Voir', 'creer' => 'Créer', 'modifier' => 'Modifier', 'supprimer' => 'Supprimer',
        'valider' => 'Valider', 'rejeter' => 'Rejeter', 'exporter' => 'Exporter', 'importer' => 'Importer',
        'generer_pdf' => 'Générer PDF', 'consulter_journal' => 'Consulter journal',
    ];

    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach ($this->modules as $module => $libModule) {
            foreach ($this->actions as $action => $libAction) {
                Permission::updateOrCreate(
                    ['name' => "$module.$action", 'guard_name' => 'web'],
                    ['module' => $module, 'action' => $action, 'libelle' => "$libModule — $libAction"]
                );
            }
        }

        $all = Permission::pluck('name')->all();

        // [permissions, type, protégé, libellé]
        $roles = [
            'administrateur' => [$all, 'systeme', true, 'Administrateur système'],
            'president' => [['*.voir', 'pap.valider', 'budget.valider', 'rapport.exporter', 'tableau_bord.voir'], 'metier', true, 'Président de la Commission'],
            'vice_president' => [['*.voir', 'pap.valider', 'budget.valider', 'engagement.valider', 'tableau_bord.voir'], 'metier', true, 'Vice-Président'],
            'secretaire_general' => [['*.voir', 'pap.valider', 'budget.valider', 'engagement.valider', 'ordonnancement.valider', 'rapport.exporter'], 'metier', true, 'Secrétaire Général'],
            'commissaire' => [['*.voir', 'pap.valider', 'budget.valider', 'suivi.valider', 'rapport.exporter'], 'metier', false, 'Commissaire'],
            'directeur' => [['*.voir', 'structure.exporter', 'pap.creer', 'pap.modifier', 'pap.supprimer', 'pap.valider', 'budget.creer', 'budget.modifier', 'budget.supprimer', 'budget.valider', 'budget.consolider', 'nomenclature.valider', 'structure_budget.valider', 'structure_budget.rejeter', 'structure_budget.consulter_journal', 'besoin.valider', 'engagement.creer', 'marche.creer', 'marche.modifier', 'contrat.creer', 'contrat.modifier', 'recommandation.creer', 'recommandation.modifier', 'formation.creer', 'formation.modifier', 'formation.supprimer', 'immobilisation.creer', 'immobilisation.modifier', 'immobilisation.supprimer', 'risques.creer', 'risques.modifier', 'risques.valider', 'incident.creer', 'incident.valider'], 'metier', false, 'Directeur'],
            'chef_service' => [['*.voir', 'besoin.creer', 'besoin.modifier', 'pap.creer', 'pap.valider', 'budget.creer', 'budget.valider', 'suivi.creer', 'recommandation.creer', 'recommandation.modifier', 'formation.creer', 'formation.modifier'], 'metier', false, 'Chef de service'],
            'gestionnaire_budgetaire' => [['*.voir', 'engagement.creer', 'engagement.modifier', 'liquidation.creer', 'ordonnancement.creer', 'paiement.creer', 'ligne_budgetaire.modifier', 'ligne_budgetaire.creer', 'ligne_budgetaire.importer', 'budget.creer', 'budget.modifier', 'budget.exporter', 'budget.consolider', 'source_financement.creer', 'source_financement.modifier', 'partenaire_financier.creer', 'partenaire_financier.modifier', 'convention_financement.creer', 'convention_financement.modifier', 'marche.creer', 'marche.modifier', 'marche.supprimer', 'contrat.creer', 'contrat.modifier', 'contrat.supprimer', 'nomenclature.creer', 'nomenclature.modifier', 'nomenclature.supprimer', 'nomenclature.exporter', 'nomenclature.importer', 'structure_budget.creer', 'structure_budget.modifier', 'structure_budget.exporter', 'structure_budget.importer', 'immobilisation.creer', 'immobilisation.modifier', 'incident.creer'], 'metier', false, 'Gestionnaire budgétaire'],
            'controleur_financier' => [['*.voir', 'pap.valider', 'budget.valider', 'budget.arbitrer', 'budget.consolider', 'engagement.valider', 'engagement.rejeter', 'liquidation.valider', 'liquidation.rejeter'], 'metier', true, 'Contrôleur financier'],
            'comptable' => [['*.voir', 'paiement.creer', 'paiement.valider', 'immobilisation.creer', 'immobilisation.modifier'], 'metier', true, 'Comptable'],
            'auditeur' => [['*.voir', 'audit.voir', 'audit.creer', 'audit.modifier', 'audit.valider', 'audit.exporter', 'audit.consulter_journal', 'structure_budget.consulter_journal', 'recommandation.creer', 'recommandation.modifier', 'risques.creer', 'risques.modifier', 'risques.supprimer', 'risques.valider', 'risques.exporter', 'controle_interne.creer', 'controle_interne.modifier', 'controle_interne.valider', 'conformite.creer', 'conformite.modifier', 'conformite.valider', 'incident.creer', 'incident.modifier', 'incident.valider'], 'metier', true, 'Auditeur'],
            'point_focal' => [['besoin.voir', 'besoin.creer', 'suivi.creer', 'indicateur.creer'], 'metier', false, 'Point focal'],
            'lecteur' => [['*.voir'], 'metier', false, 'Lecteur (consultation)'],
        ];

        foreach ($roles as $name => [$perms, $type, $protege, $libelle]) {
            $role = Role::findOrCreate($name, 'web');
            $role->forceFill(['type' => $type, 'protege' => $protege, 'libelle' => $libelle])->save();
            $role->syncPermissions($this->resoudre($perms, $all));
        }

        $this->mirrorAdministratifPermissions();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /** Duplique structure.* vers administratif.* pour compatibilité checklist ERP (phase A8). */
    private function mirrorAdministratifPermissions(): void
    {
        $actions = ['voir', 'creer', 'modifier', 'supprimer', 'exporter', 'importer'];

        foreach (Role::all() as $role) {
            $ajouts = [];
            foreach ($actions as $action) {
                if ($role->hasPermissionTo("structure.$action")) {
                    $ajouts[] = "administratif.$action";
                }
            }
            if ($ajouts !== []) {
                $role->givePermissionTo($ajouts);
            }
        }
    }

    /** Étend les jokers « *.action » à tous les modules et filtre sur les permissions existantes. */
    private function resoudre(array $perms, array $all): array
    {
        $expanded = [];
        foreach ($perms as $p) {
            if (str_starts_with($p, '*.')) {
                $action = substr($p, 2);
                foreach (array_keys($this->modules) as $m) {
                    $expanded[] = "$m.$action";
                }
            } else {
                $expanded[] = $p;
            }
        }

        return array_values(array_unique(array_intersect($expanded, $all)));
    }
}
