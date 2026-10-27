<?php

namespace App\Services\Audit;

use App\Models\Structure;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Audit du referentiel organisationnel maitre.
 *
 * Le module Structures est la source unique pour departement, direction,
 * service, responsable et unite organisationnelle. Ce service reste non
 * destructif: il detecte les ecarts et propose les corrections.
 */
class StructureMasterDataAuditService
{
    /** @var list<string> */
    private const TABLES_SYSTEME = [
        'audit_integrite_data',
        'cache',
        'cache_locks',
        'failed_jobs',
        'job_batches',
        'jobs',
        'migrations',
        'password_reset_tokens',
        'sessions',
    ];

    /** @var list<string> */
    private const TABLES_PIVOT = [
        'ged_document_tags',
        'ligne_source_financement',
        'model_has_permissions',
        'model_has_roles',
        'permission_role',
        'profil_role',
        'role_has_permissions',
        'user_profils',
        'user_structures',
    ];

    /** @var list<string> */
    private const TABLES_REFERENTIEL_NON_ORG = [
        'budget_articles',
        'budget_paragraphes',
        'budget_rubriques',
        'budget_titres',
        'categories_recettes',
        'categories_risques',
        'etats_membres',
        'exercices',
        'fonctions',
        'ged_categories',
        'ged_tags',
        'ged_types_documents',
        'incident_categories',
        'notification_channels',
        'notification_templates',
        'notification_types',
        'partenaires_financiers',
        'profils',
        'roles',
        'sources_financement',
        'sources_verification',
        'types_indicateurs',
        'unites_mesure',
        'webhook_events',
    ];

    /** @var list<string> */
    private const TABLES_METIER_ATTENDUES = [
        'activites',
        'alertes',
        'besoin_consolidations',
        'budgets',
        'budget_arbitrages',
        'budget_preparations',
        'contrats',
        'controles_internes',
        'delegations',
        'expressions_besoin',
        'formations',
        'ged_documents',
        'ged_dossiers',
        'immobilisations',
        'incidents',
        'interims',
        'lignes_budgetaires',
        'marches',
        'missions_audit',
        'missions_suivi',
        'non_conformites',
        'paps',
        'plans_amelioration',
        'produits',
        'programmes',
        'recettes',
        'recommandations',
        'risques',
        'users',
    ];

    /** @var list<string> */
    private const COLONNES_ORG_TEXTUELLES = [
        'departement',
        'departement_code',
        'direction',
        'direction_code',
        'service',
        'service_code',
        'structure',
        'structure_code',
        'unite_organisationnelle',
        'unite_org',
    ];

    /**
     * @return array{synthese: array<string, mixed>, cartographie: array<string, mixed>, anomalies: list<array<string, mixed>>, remediation: list<array<string, string>>}
     */
    public function executer(?int $exerciceId = null): array
    {
        $tables = collect($this->listerTables())->sort()->values();

        $cartographie = [
            'referentiel_maitre' => $this->cartographierReferentielMaitre(),
            'tables_avec_structure_id' => [],
            'tables_sans_structure_id_attendu' => [],
            'colonnes_textuelles_organisationnelles' => [],
            'orphelins_structure_id' => [],
            'rattachements_alias' => [],
            'rattachements_inactifs' => [],
        ];
        $anomalies = [];

        foreach ($tables as $table) {
            if (! is_string($table) || $this->ignorerTable($table)) {
                continue;
            }

            $colonnes = Schema::getColumnListing($table);

            if (in_array('structure_id', $colonnes, true)) {
                $cartographie['tables_avec_structure_id'][] = $table;
                $this->auditerStructureId($table, $cartographie, $anomalies);
            } elseif ($this->tableMetierAttendStructure($table)) {
                $cartographie['tables_sans_structure_id_attendu'][] = $table;
                $anomalies[] = [
                    'niveau' => 'warning',
                    'code' => 'TABLE_METIER_SANS_STRUCTURE_ID',
                    'message' => "La table {$table} ne porte pas de structure_id alors qu'elle manipule des donnees metier organisationnelles.",
                    'table' => $table,
                    'correction' => 'Ajouter structure_id nullable avec FK vers structures, puis backfiller depuis le parent metier lorsque possible.',
                ];
            }

            $colonnesTextuelles = array_values(array_intersect($colonnes, self::COLONNES_ORG_TEXTUELLES));
            foreach ($colonnesTextuelles as $colonne) {
                $cartographie['colonnes_textuelles_organisationnelles'][] = [
                    'table' => $table,
                    'colonne' => $colonne,
                ];

                if ($colonne !== 'structure_code') {
                    $anomalies[] = [
                        'niveau' => 'warning',
                        'code' => 'REFERENCE_ORG_TEXTUELLE',
                        'message' => "La colonne {$table}.{$colonne} stocke une reference organisationnelle textuelle.",
                        'table' => $table,
                        'colonne' => $colonne,
                        'correction' => 'Remplacer par structure_id ou deriver la valeur depuis structures au rendu/export.',
                    ];
                }
            }
        }

        $this->auditerHierarchieStructures($anomalies);

        return [
            'synthese' => [
                'exercice_id' => $exerciceId,
                'tables_auditees' => $tables->count(),
                'tables_avec_structure_id' => count($cartographie['tables_avec_structure_id']),
                'tables_sans_structure_id_attendu' => count($cartographie['tables_sans_structure_id_attendu']),
                'colonnes_textuelles_organisationnelles' => count($cartographie['colonnes_textuelles_organisationnelles']),
                'orphelins_structure_id' => collect($cartographie['orphelins_structure_id'])->sum('total'),
                'rattachements_alias' => collect($cartographie['rattachements_alias'])->sum('total'),
                'rattachements_inactifs' => collect($cartographie['rattachements_inactifs'])->sum('total'),
            ],
            'cartographie' => $cartographie,
            'anomalies' => $anomalies,
            'remediation' => $this->planRemediation(),
        ];
    }

    /**
     * Remplace les rattachements vers des alias par la structure officielle.
     *
     * @return array{dry_run: bool, total: int, tables: list<array<string, mixed>>}
     */
    public function appliquerAliasOfficiels(bool $dryRun = true): array
    {
        if (! Schema::hasColumn('structures', 'structure_officielle_id')) {
            return ['dry_run' => $dryRun, 'total' => 0, 'tables' => []];
        }

        $alias = Structure::query()
            ->whereNotNull('structure_officielle_id')
            ->pluck('structure_officielle_id', 'id');

        if ($alias->isEmpty()) {
            return ['dry_run' => $dryRun, 'total' => 0, 'tables' => []];
        }

        $tables = collect($this->listerTables())
            ->filter(fn (mixed $table) => is_string($table) && Schema::hasColumn($table, 'structure_id'))
            ->sort()
            ->values();

        $resultats = [];
        $total = 0;

        DB::transaction(function () use ($tables, $alias, $dryRun, &$resultats, &$total): void {
            foreach ($tables as $table) {
                $count = DB::table($table)->whereIn('structure_id', $alias->keys())->count();
                if ($count === 0) {
                    continue;
                }

                $total += $count;
                $resultats[] = ['table' => $table, 'lignes' => $count];

                if ($dryRun) {
                    continue;
                }

                foreach ($alias as $aliasId => $officielId) {
                    DB::table($table)
                        ->where('structure_id', (int) $aliasId)
                        ->update(['structure_id' => (int) $officielId]);
                }
            }
        });

        return ['dry_run' => $dryRun, 'total' => $total, 'tables' => $resultats];
    }

    private function auditerStructureId(string $table, array &$cartographie, array &$anomalies): void
    {
        $orphelins = DB::table($table)
            ->whereNotNull("{$table}.structure_id")
            ->leftJoin('structures', "{$table}.structure_id", '=', 'structures.id')
            ->whereNull('structures.id')
            ->count();

        if ($orphelins > 0) {
            $cartographie['orphelins_structure_id'][] = ['table' => $table, 'total' => $orphelins];
            $anomalies[] = [
                'niveau' => 'critique',
                'code' => 'STRUCTURE_ID_ORPHELIN',
                'message' => "{$orphelins} ligne(s) de {$table} pointent vers une structure inexistante.",
                'table' => $table,
                'correction' => 'Backfiller structure_id vers une structure officielle valide avant d activer une contrainte stricte.',
            ];
        }

        if (Schema::hasColumn('structures', 'structure_officielle_id')) {
            $alias = DB::table($table)
                ->join('structures', "{$table}.structure_id", '=', 'structures.id')
                ->whereNotNull("{$table}.structure_id")
                ->whereNotNull('structures.structure_officielle_id')
                ->count();

            if ($alias > 0) {
                $cartographie['rattachements_alias'][] = ['table' => $table, 'total' => $alias];
                $anomalies[] = [
                    'niveau' => 'info',
                    'code' => 'STRUCTURE_ALIAS_UTILISEE',
                    'message' => "{$alias} ligne(s) de {$table} utilisent une structure alias au lieu de la structure officielle.",
                    'table' => $table,
                    'correction' => 'Remplacer structure_id par structures.structure_officielle_id pour officialiser le rattachement.',
                ];
            }
        }

        if (Schema::hasColumn('structures', 'actif')) {
            $inactifs = DB::table($table)
                ->join('structures', "{$table}.structure_id", '=', 'structures.id')
                ->whereNotNull("{$table}.structure_id")
                ->where('structures.actif', false)
                ->count();

            if ($inactifs > 0) {
                $cartographie['rattachements_inactifs'][] = ['table' => $table, 'total' => $inactifs];
                $anomalies[] = [
                    'niveau' => 'warning',
                    'code' => 'STRUCTURE_INACTIVE_UTILISEE',
                    'message' => "{$inactifs} ligne(s) de {$table} utilisent une structure inactive.",
                    'table' => $table,
                    'correction' => 'Verifier l historique; conserver si archive, sinon reaffecter vers une structure active officielle.',
                ];
            }
        }
    }

    /** @param  list<array<string, mixed>>  $anomalies */
    private function auditerHierarchieStructures(array &$anomalies): void
    {
        $typesValides = [
            'commission',
            'presidence',
            'vice_presidence',
            'secretariat_general',
            'commissariat',
            'departement',
            'direction',
            'service',
            'cellule',
        ];

        $typesInvalides = Structure::query()
            ->whereNotNull('type')
            ->whereNotIn('type', $typesValides)
            ->count();

        if ($typesInvalides > 0) {
            $anomalies[] = [
                'niveau' => 'critique',
                'code' => 'STRUCTURE_TYPE_INVALIDE',
                'message' => "{$typesInvalides} structure(s) ont un type hors referentiel officiel.",
                'correction' => 'Normaliser le type selon la typologie officielle du module Structures.',
            ];
        }

        $sansParent = Structure::query()
            ->whereIn('type', ['departement', 'direction', 'service'])
            ->whereNull('parent_id')
            ->count();

        if ($sansParent > 0) {
            $anomalies[] = [
                'niveau' => 'critique',
                'code' => 'HIERARCHIE_STRUCTURE_INCOMPLETE',
                'message' => "{$sansParent} departement/direction/service sans parent hierarchique.",
                'correction' => 'Renseigner parent_id pour reconstruire Commission > Departement > Direction > Service.',
            ];
        }
    }

    /** @return array<string, mixed> */
    private function cartographierReferentielMaitre(): array
    {
        return [
            'table' => 'structures',
            'total' => Structure::count(),
            'actives' => Schema::hasColumn('structures', 'actif') ? Structure::where('actif', true)->count() : null,
            'par_type' => Structure::query()
                ->select('type', DB::raw('COUNT(*) as total'))
                ->groupBy('type')
                ->orderBy('type')
                ->pluck('total', 'type')
                ->all(),
            'avec_responsable' => Schema::hasColumn('structures', 'responsable_user_id')
                ? Structure::whereNotNull('responsable_user_id')->count()
                : null,
            'alias_demo' => Schema::hasColumn('structures', 'structure_officielle_id')
                ? Structure::whereNotNull('structure_officielle_id')->count()
                : null,
        ];
    }

    private function ignorerTable(string $table): bool
    {
        return in_array($table, self::TABLES_SYSTEME, true)
            || in_array($table, self::TABLES_PIVOT, true);
    }

    private function tableMetierAttendStructure(string $table): bool
    {
        if (in_array($table, self::TABLES_REFERENTIEL_NON_ORG, true)) {
            return false;
        }

        if (in_array($table, self::TABLES_METIER_ATTENDUES, true)) {
            return true;
        }

        return false;
    }

    /** @return list<string> */
    private function listerTables(): array
    {
        return array_values(array_filter(
            Schema::getTableListing(),
            fn (mixed $table) => is_string($table) && $table !== ''
        ));
    }

    /** @return list<array<string, string>> */
    private function planRemediation(): array
    {
        return [
            [
                'priorite' => 'P1',
                'action' => 'Corriger les structure_id orphelins et les types de structures invalides.',
            ],
            [
                'priorite' => 'P1',
                'action' => 'Backfiller les tables metier sans structure_id depuis leur parent fonctionnel.',
            ],
            [
                'priorite' => 'P2',
                'action' => 'Remplacer les champs departement/direction/service textuels par des lectures derivees de structures.',
            ],
            [
                'priorite' => 'P2',
                'action' => 'Normaliser les exports pour afficher code, libelle, departement, direction, service et responsable officiels.',
            ],
            [
                'priorite' => 'P3',
                'action' => 'Rendre obligatoire structure_id dans les formulaires metier apres reprise des donnees historiques.',
            ],
        ];
    }
}
