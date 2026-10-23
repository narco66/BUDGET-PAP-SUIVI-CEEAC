<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\Route;

/**
 * Profilage des tableaux de bord par persona institutionnel (P1) : à partir du rôle de
 * l'utilisateur, expose la liste ordonnée des cockpits pertinents (exécutif, stratégique,
 * opérationnel, financier…). Réutilise le RBAC existant — aucun nouveau dashboard, seulement
 * une mise en avant ciblée des dashboards déjà disponibles.
 */
final class PersonaTableauxBord
{
    /** @var array<string, string> */
    private const PERSONAS = [
        'executif' => 'Exécutif',
        'pilotage' => 'Pilotage',
        'operationnel' => 'Opérationnel',
        'budgetaire' => 'Budgétaire',
        'financier' => 'Financier',
        'audit' => 'Audit & contrôle',
        'administration' => 'Administration',
        'consultation' => 'Consultation',
    ];

    /**
     * Catalogue des dashboards : clé → [libellé, route, icône, description, permissions requises].
     *
     * @var array<string, array{0:string,1:string,2:string,3:string,4:list<string>}>
     */
    private const CATALOGUE = [
        'executif' => ['Cockpit exécutif', 'reporting.cockpit', 'bi-speedometer2', 'Vue stratégique globale, alertes critiques et KPI exécutifs', ['reporting.view', 'tableau_bord.voir', 'rapport.voir']],
        'decisionnel' => ['Tableaux de bord décisionnels', 'tableaux.index', 'bi-grid-1x2', 'Exécution budgétaire, indicateurs, consommation par structure', ['tableau_bord.voir']],
        'strategique' => ['Pilotage stratégique', 'strategie.dashboard.decisionnel', 'bi-diagram-3', 'Performance par pilier, axe, programme et département', ['strategie.view']],
        'pap' => ['Cockpit PAP', 'paps.dashboard', 'bi-list-task', 'Activités, tâches, échéances et alertes opérationnelles', ['pap.voir']],
        'financier' => ['Suivi financier', 'suivi-financier.index', 'bi-cash-stack', 'Taux d’exécution, restes à payer/engager, anomalies', ['suivi_financier.view', 'tableau_bord.voir']],
        'tresorerie' => ['Trésorerie unifiée', 'tableaux.tresorerie', 'bi-bank', 'Flux recettes/dépenses et position de trésorerie', ['tableau_bord.voir']],
        'depense' => ['Cockpit dépense', 'depense.cockpit', 'bi-receipt', 'Chaîne de la dépense et engagements en cours', ['engagement.voir']],
        'reporting' => ['Reporting & analyse', 'reporting.index', 'bi-bar-chart', 'Catalogue des rapports institutionnels et exports', ['reporting.view', 'rapport.voir']],
        'suivi_eval' => ['Suivi-évaluation', 'suivi-evaluation.index', 'bi-clipboard-check', 'Missions, évaluations périodiques, recommandations', ['suivi_evaluation.voir']],
        'workflow' => ['Performance workflow', 'workflow.dashboard', 'bi-diagram-2', 'Délais de traitement, circuits de validation, audit', ['workflow.view']],
    ];

    /**
     * Profils par rôle : rôle → [clé persona, clés de dashboards par ordre de pertinence].
     *
     * @var array<string, array{0:string,1:list<string>}>
     */
    private const PROFILS = [
        'president' => ['executif', ['executif', 'decisionnel', 'strategique']],
        'vice_president' => ['executif', ['executif', 'decisionnel', 'strategique']],
        'secretaire_general' => ['executif', ['executif', 'decisionnel', 'strategique', 'workflow']],
        'commissaire' => ['executif', ['executif', 'strategique', 'decisionnel']],
        'directeur' => ['pilotage', ['strategique', 'pap', 'decisionnel', 'financier']],
        'chef_service' => ['operationnel', ['pap', 'suivi_eval', 'decisionnel']],
        'point_focal' => ['operationnel', ['pap', 'suivi_eval']],
        'gestionnaire_budgetaire' => ['budgetaire', ['financier', 'tresorerie', 'decisionnel', 'depense', 'pap']],
        'controleur_financier' => ['financier', ['financier', 'tresorerie', 'decisionnel', 'workflow']],
        'comptable' => ['financier', ['financier', 'tresorerie', 'decisionnel']],
        'auditeur' => ['audit', ['reporting', 'workflow', 'financier']],
        'lecteur' => ['consultation', ['decisionnel', 'reporting']],
        'administrateur' => ['administration', ['executif', 'decisionnel', 'strategique', 'pap', 'financier', 'tresorerie', 'reporting', 'suivi_eval', 'workflow', 'depense']],
    ];

    public static function clePersona(User $user): string
    {
        $role = $user->getRoleNames()->first(fn ($r) => isset(self::PROFILS[$r]));

        return self::PROFILS[$role][0] ?? 'consultation';
    }

    /** @return list<string> */
    public static function widgets(User $user, string $context = 'widgets'): array
    {
        $cle = self::clePersona($user);
        $map = config("dashboard-persona.{$context}", []);
        $widgets = $map[$cle] ?? $map['consultation'] ?? [];

        if (in_array('*', $widgets, true)) {
            $allKey = $context === 'tableaux' ? 'all_tableaux' : 'all_widgets';

            return config("dashboard-persona.{$allKey}", []);
        }

        return $widgets;
    }

    public static function aWidget(User $user, string $widget, string $context = 'widgets'): bool
    {
        $widgets = self::widgets($user, $context);

        return in_array('*', $widgets, true) || in_array($widget, $widgets, true);
    }

    /**
     * @return array{persona:string, persona_key:string, cartes:list<array{cle:string,label:string,route:string,icone:string,desc:string}>, widgets:list<string>, tableaux_widgets:list<string>}
     */
    public static function pour(User $user): array
    {
        $cle = self::clePersona($user);
        $role = $user->getRoleNames()->first(fn ($r) => isset(self::PROFILS[$r]));
        $cles = $role ? self::PROFILS[$role][1] : self::PROFILS['lecteur'][1];

        $cartes = [];
        foreach ($cles as $itemCle) {
            if (! isset(self::CATALOGUE[$itemCle])) {
                continue;
            }
            [$label, $route, $icone, $desc, $abilities] = self::CATALOGUE[$itemCle];

            if (! Route::has($route)) {
                continue;
            }
            if (! self::autorise($user, $abilities)) {
                continue;
            }

            $cartes[] = ['cle' => $itemCle, 'label' => $label, 'route' => $route, 'icone' => $icone, 'desc' => $desc];
        }

        return [
            'persona' => self::PERSONAS[$cle] ?? 'Consultation',
            'persona_key' => $cle,
            'cartes' => $cartes,
            'widgets' => self::widgets($user),
            'tableaux_widgets' => self::widgets($user, 'tableaux'),
        ];
    }

    /** @param  list<string>  $abilities */
    private static function autorise(User $user, array $abilities): bool
    {
        if ($abilities === []) {
            return true;
        }

        foreach ($abilities as $ability) {
            if ($user->can($ability)) {
                return true;
            }
        }

        return false;
    }
}
