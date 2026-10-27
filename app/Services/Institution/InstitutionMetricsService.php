<?php

namespace App\Services\Institution;

use App\Models\Exercice;
use App\Models\Structure;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class InstitutionMetricsService
{
    public function indicateurs(?int $exerciceId = null): array
    {
        $exerciceId ??= Exercice::query()->where('statut', 'ouvert')->value('id')
            ?? Exercice::query()->latest('annee')->value('id');

        return [
            'effectifs_departements' => $this->effectifsParType('departement'),
            'effectifs_directions' => $this->effectifsParType('direction'),
            'effectifs_services' => $this->effectifsParType('service'),
            'budget_departements' => $this->budgetParDepartement($exerciceId),
            'pap_departements' => $this->papParDepartement($exerciceId),
            'execution_departements' => $this->executionParDepartement($exerciceId),
            'projets_departements' => $this->projetsParDepartement($exerciceId),
        ];
    }

    public function lineage(?Structure $structure): array
    {
        $lineage = ['departement' => null, 'direction' => null, 'service' => null, 'responsable' => null];

        for ($current = $structure; $current; $current = $current->parent) {
            if (array_key_exists($current->type, $lineage) && ! $lineage[$current->type]) {
                $lineage[$current->type] = $current;
            }

            if (! $lineage['responsable'] && $current->responsable) {
                $lineage['responsable'] = $current->responsable;
            }
        }

        return $lineage;
    }

    private function effectifsParType(string $type): Collection
    {
        return Structure::query()
            ->leftJoin('users as u', 'u.structure_id', '=', 'structures.id')
            ->where('structures.type', $type)
            ->whereNull('structures.deleted_at')
            ->groupBy('structures.id', 'structures.code', 'structures.libelle')
            ->orderBy('structures.ordre')
            ->get([
                'structures.code',
                'structures.libelle',
                DB::raw('COUNT(u.id) as effectif'),
            ]);
    }

    private function budgetParDepartement(?int $exerciceId): Collection
    {
        return $this->aggregateByDepartment('lignes_budgetaires', 'lb', $exerciceId, [
            DB::raw('COALESCE(SUM(lb.montant_ouvert), 0) as ouvert'),
            DB::raw('COALESCE(SUM(COALESCE(lb.montant_revise, lb.montant_ouvert)), 0) as alloue'),
            DB::raw('COALESCE(SUM(lb.montant_engage), 0) as engage'),
            DB::raw('COALESCE(SUM(lb.montant_paye), 0) as paye'),
        ]);
    }

    private function papParDepartement(?int $exerciceId): Collection
    {
        if (! $exerciceId) {
            return collect();
        }

        return app(\App\Services\Pap\PapPerformanceService::class)
            ->consolidationParStructure($exerciceId)
            ->map(fn (array $r) => (object) [
                'code' => $r['code'],
                'libelle' => $r['libelle'],
                'activites' => $r['activites'],
                'taux_physique' => $r['taux_physique'],
                'taux_financier' => $r['taux_financier'],
            ]);
    }

    private function executionParDepartement(?int $exerciceId): Collection
    {
        return $this->budgetParDepartement($exerciceId)->map(function ($row) {
            $alloue = (float) $row->alloue;
            $row->taux_execution = $alloue > 0 ? round((float) $row->paye / $alloue * 100, 1) : 0.0;
            $row->performance = round(((float) $row->taux_execution + min(100, (float) $row->engage / max(1, $alloue) * 100)) / 2, 1);

            return $row;
        });
    }

    private function projetsParDepartement(?int $exerciceId): Collection
    {
        return $this->aggregateByDepartment('activites', 'a', $exerciceId, [
            DB::raw('COUNT(DISTINCT a.id) as projets'),
        ]);
    }

    private function aggregateByDepartment(string $table, string $alias, ?int $exerciceId, array $columns): Collection
    {
        $query = DB::table("{$table} as {$alias}")
            ->leftJoin('structures as s', "{$alias}.structure_id", '=', 's.id')
            ->leftJoin('structures as p1', 's.parent_id', '=', 'p1.id')
            ->leftJoin('structures as p2', 'p1.parent_id', '=', 'p2.id')
            ->leftJoin('structures as p3', 'p2.parent_id', '=', 'p3.id')
            ->leftJoin('structures as dep', function ($join) {
                $join->on('dep.id', '=', DB::raw("
                    CASE
                        WHEN s.type = 'departement' THEN s.id
                        WHEN p1.type = 'departement' THEN p1.id
                        WHEN p2.type = 'departement' THEN p2.id
                        WHEN p3.type = 'departement' THEN p3.id
                        ELSE NULL
                    END
                "));
            })
            ->whereNull("{$alias}.deleted_at")
            ->groupBy('dep.id', 'dep.code', 'dep.libelle')
            ->orderBy('dep.code');

        if ($exerciceId && DB::getSchemaBuilder()->hasColumn($table, 'exercice_id')) {
            $query->where("{$alias}.exercice_id", $exerciceId);
        }

        return $query->get(array_merge([
            DB::raw('COALESCE(dep.code, \'NON-RATTACHE\') as code'),
            DB::raw('COALESCE(dep.libelle, \'Non rattache\') as libelle'),
        ], $columns));
    }
}
