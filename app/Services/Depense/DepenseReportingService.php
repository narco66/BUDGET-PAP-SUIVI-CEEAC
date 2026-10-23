<?php

namespace App\Services\Depense;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/** Ventilation reporting chaîne dépense par tâche PAP (phases D2 / P2). */
class DepenseReportingService
{
    /** @return Collection<int, object> */
    public function ventilerParTache(int $exerciceId, ?int $tacheId = null): Collection
    {
        $query = DB::table('engagements as e')
            ->leftJoin('taches as t', 't.id', '=', 'e.tache_id')
            ->leftJoin('expressions_besoin as b', 'b.id', '=', 'e.expression_besoin_id')
            ->where('e.exercice_id', $exerciceId)
            ->whereNull('e.deleted_at')
            ->whereIn('e.statut', ['vise', 'soumis'])
            ->groupBy('e.tache_id', 't.code', 't.intitule', 'b.priorite')
            ->selectRaw('
                e.tache_id,
                t.code as tache_code,
                t.intitule as tache_intitule,
                COUNT(DISTINCT e.id) as nb_engagements,
                COALESCE(SUM(e.montant_total), 0) as montant_engage,
                COALESCE(SUM(b.montant_estime), 0) as montant_besoins
            ');

        if ($tacheId) {
            $query->where('e.tache_id', $tacheId);
        }

        return $query->get();
    }

    /** Chaîne complète par tâche : engagé, liquidé, ordonnancé, payé (phase P2). */
    public function executionParTache(int $exerciceId, ?int $tacheId = null): Collection
    {
        $engage = $this->ventilerParTache($exerciceId, $tacheId)->keyBy('tache_id');

        $chaineQuery = DB::table('paiements as p')
            ->join('ordonnancements as o', 'o.id', '=', 'p.ordonnancement_id')
            ->join('liquidations as l', 'l.id', '=', 'o.liquidation_id')
            ->join('engagements as e', 'e.id', '=', 'l.engagement_id')
            ->leftJoin('taches as t', 't.id', '=', 'e.tache_id')
            ->where('e.exercice_id', $exerciceId)
            ->where('p.statut', 'valide')
            ->whereNull('p.deleted_at')
            ->whereNull('e.deleted_at')
            ->groupBy('e.tache_id', 't.code', 't.intitule')
            ->selectRaw('
                e.tache_id,
                t.code as tache_code,
                t.intitule as tache_intitule,
                COALESCE(SUM(l.montant), 0) as montant_liquide,
                COALESCE(SUM(o.montant), 0) as montant_ordonnance,
                COALESCE(SUM(p.montant), 0) as montant_paye
            ');

        if ($tacheId) {
            $chaineQuery->where('e.tache_id', $tacheId);
        }

        $chaine = $chaineQuery->get()->keyBy('tache_id');
        $ids = $engage->keys()->merge($chaine->keys())->unique()->filter();

        return $ids->map(function ($id) use ($engage, $chaine) {
            $e = $engage->get($id);
            $c = $chaine->get($id);

            return (object) [
                'tache_id' => $id,
                'tache_code' => $e->tache_code ?? $c->tache_code ?? '—',
                'tache_intitule' => $e->tache_intitule ?? $c->tache_intitule ?? '—',
                'nb_engagements' => (int) ($e->nb_engagements ?? 0),
                'montant_engage' => (float) ($e->montant_engage ?? 0),
                'montant_besoins' => (float) ($e->montant_besoins ?? 0),
                'montant_liquide' => (float) ($c->montant_liquide ?? 0),
                'montant_ordonnance' => (float) ($c->montant_ordonnance ?? 0),
                'montant_paye' => (float) ($c->montant_paye ?? 0),
            ];
        })->values();
    }

    /** @return array{nb:int,montant:float} */
    public function syntheseBesoinsParTache(int $exerciceId, ?int $tacheId = null): array
    {
        $q = \App\Models\ExpressionBesoin::where('exercice_id', $exerciceId);

        if ($tacheId) {
            $q->where('tache_id', $tacheId);
        }

        return [
            'nb' => $q->count(),
            'montant' => (float) $q->sum('montant_estime'),
        ];
    }
}
