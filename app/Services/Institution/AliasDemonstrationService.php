<?php

namespace App\Services\Institution;

use App\Models\Structure;
use App\Models\UserAccessScope;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Rapprochement des alias historiques de démonstration vers le cadre organique officiel (phase A3). */
class AliasDemonstrationService
{
    /** @var array<string, string> code alias => code structure officielle */
    public const ALIAS_VERS_OFFICIEL = [
        'DEPT-PLAN' => 'DSG',
        'DIR-BUDGET' => 'DIR-PPB',
        'SVC-EXEC' => 'SVC-BUDGET',
    ];

    /** @var list<string> */
    private const TABLES_STRUCTURE_ID = [
        'users',
        'user_structures',
        'programmes',
        'produits',
        'activites',
        'lignes_budgetaires',
        'expressions_besoin',
        'marches',
        'contrats',
        'formations',
        'recommandations',
        'ged_documents',
    ];

    /** @return array{aliases: int, liens: int, hierarchies: int} */
    public function rapprocher(): array
    {
        $stats = ['aliases' => 0, 'liens' => 0, 'hierarchies' => 0];

        $officiels = Structure::query()
            ->whereIn('code', array_values(self::ALIAS_VERS_OFFICIEL))
            ->get()
            ->keyBy('code');

        foreach (self::ALIAS_VERS_OFFICIEL as $codeAlias => $codeOfficiel) {
            $alias = Structure::where('code', $codeAlias)->first();
            $officiel = $officiels->get($codeOfficiel);

            if (! $alias || ! $officiel) {
                continue;
            }

            $stats['aliases']++;

            if ((int) $alias->structure_officielle_id !== (int) $officiel->id) {
                $alias->update(['structure_officielle_id' => $officiel->id]);
                $stats['liens']++;
            }
        }

        $stats['hierarchies'] = $this->normaliserHierarchiesAlias();

        return $stats;
    }

    /** @return list<string> */
    public function listerAnomalies(): array
    {
        $anomalies = [];

        foreach (self::ALIAS_VERS_OFFICIEL as $codeAlias => $codeOfficiel) {
            $alias = Structure::where('code', $codeAlias)->first();
            $officiel = Structure::where('code', $codeOfficiel)->first();

            if (! $alias) {
                continue;
            }

            if (! $officiel) {
                $anomalies[] = "{$codeAlias} — structure officielle « {$codeOfficiel} » introuvable";

                continue;
            }

            if ((int) $alias->structure_officielle_id !== (int) $officiel->id) {
                $anomalies[] = "{$codeAlias} — non rapproche de {$codeOfficiel}";
            }

            $msg = $this->verifierHierarchieAlias($codeAlias, $alias);
            if ($msg) {
                $anomalies[] = $msg;
            }
        }

        return $anomalies;
    }

    /**
     * Réoriente les clés étrangères métier des alias vers leur structure officielle.
     *
     * @return array<string, int>
     */
    public function reorienterDonneesMetier(bool $dryRun = false): array
    {
        $stats = [];

        foreach (self::ALIAS_VERS_OFFICIEL as $codeAlias => $codeOfficiel) {
            $alias = Structure::where('code', $codeAlias)->first();
            $officiel = Structure::where('code', $codeOfficiel)->first();

            if (! $alias || ! $officiel || $alias->id === $officiel->id) {
                continue;
            }

            foreach (self::TABLES_STRUCTURE_ID as $table) {
                if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'structure_id')) {
                    continue;
                }

                $query = DB::table($table)->where('structure_id', $alias->id);
                $count = (clone $query)->count();

                if ($count === 0) {
                    continue;
                }

                if (! $dryRun) {
                    $query->update(['structure_id' => $officiel->id]);
                }

                $stats[$table] = ($stats[$table] ?? 0) + $count;
            }

            if (! $dryRun) {
                UserAccessScope::query()
                    ->whereIn('type', ['departement', 'direction', 'service'])
                    ->where('scope_id', $alias->id)
                    ->update(['scope_id' => $officiel->id]);
            }

            $scopeCount = UserAccessScope::query()
                ->whereIn('type', ['departement', 'direction', 'service'])
                ->where('scope_id', $alias->id)
                ->count();

            if ($scopeCount > 0) {
                $stats['user_access_scopes'] = ($stats['user_access_scopes'] ?? 0) + $scopeCount;
            }
        }

        return $stats;
    }

    private function normaliserHierarchiesAlias(): int
    {
        $corrections = 0;
        $sg = Structure::where('code', 'SG')->first();
        $dirPpb = Structure::where('code', 'DIR-PPB')->first();
        $dirBudget = Structure::where('code', 'DIR-BUDGET')->first();
        $deptPlan = Structure::where('code', 'DEPT-PLAN')->first();
        $svcExec = Structure::where('code', 'SVC-EXEC')->first();

        if ($deptPlan && $sg && (int) $deptPlan->parent_id !== (int) $sg->id) {
            $deptPlan->update([
                'parent_id' => $sg->id,
                'type' => 'departement',
                'niveau' => $sg->niveau + 1,
                'actif' => true,
            ]);
            $corrections++;
        }

        if ($dirBudget && $dirPpb && (int) $dirBudget->parent_id !== (int) $dirPpb->id) {
            $dirBudget->update([
                'parent_id' => $dirPpb->id,
                'type' => 'direction',
                'niveau' => $dirPpb->niveau + 1,
                'actif' => true,
            ]);
            $corrections++;
        }

        if ($svcExec && $dirBudget && (int) $svcExec->parent_id !== (int) $dirBudget->id) {
            $svcExec->update([
                'parent_id' => $dirBudget->id,
                'type' => 'service',
                'niveau' => $dirBudget->niveau + 1,
                'actif' => true,
            ]);
            $corrections++;
        }

        return $corrections;
    }

    private function verifierHierarchieAlias(string $codeAlias, Structure $alias): ?string
    {
        return match ($codeAlias) {
            'DEPT-PLAN' => $alias->parent?->code === 'SG'
                ? null
                : "{$codeAlias} — doit etre rattache au Secretariat General (SG)",
            'DIR-BUDGET' => $alias->parent?->code === 'DIR-PPB'
                ? null
                : "{$codeAlias} — doit etre rattache a DIR-PPB",
            'SVC-EXEC' => $alias->parent?->code === 'DIR-BUDGET'
                ? null
                : "{$codeAlias} — doit etre rattache a DIR-BUDGET",
            default => null,
        };
    }
}
