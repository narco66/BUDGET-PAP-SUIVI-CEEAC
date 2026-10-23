<?php

namespace App\Services\Interop;

use App\Models\ApiLog;
use App\Models\ApiMetric;
use App\Models\ConnectedApp;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/** Agrégation KPI monitoring API (I-P3). */
class ApiMonitoringService
{
    /** @return array<string, mixed> */
    public function kpis(?Carbon $depuis = null): array
    {
        $depuis ??= now()->subDay();
        $base = ApiLog::where('created_at', '>=', $depuis);

        $total = (clone $base)->count();
        $latence = (clone $base)->whereNotNull('duree_ms');

        return [
            'periode_depuis' => $depuis->toIso8601String(),
            'appels_total' => $total,
            'appels_succes' => (clone $base)->where('statut_metier', 'succes')->count(),
            'appels_erreur' => (clone $base)->where('statut_metier', 'erreur')->count(),
            'appels_rejet' => (clone $base)->where('statut_metier', 'rejet')->count(),
            'latence_moy_ms' => (int) round((float) $latence->avg('duree_ms')),
            'latence_max_ms' => (int) ((clone $latence)->max('duree_ms') ?? 0),
            'taux_succes' => $total > 0
                ? round((clone $base)->where('statut_metier', 'succes')->count() / $total * 100, 1)
                : 0,
        ];
    }

    /** @return Collection<int, object> */
    public function topChemins(int $limit = 10, ?Carbon $depuis = null): Collection
    {
        $depuis ??= now()->subDay();

        return ApiLog::query()
            ->select('chemin', DB::raw('COUNT(*) as total'), DB::raw('AVG(duree_ms) as latence_moy'))
            ->where('created_at', '>=', $depuis)
            ->groupBy('chemin')
            ->orderByDesc('total')
            ->limit($limit)
            ->get();
    }

    /** @return Collection<int, object> */
    public function parApplication(?Carbon $depuis = null): Collection
    {
        $depuis ??= now()->subDay();

        return ApiLog::query()
            ->select('connected_app_id', DB::raw('COUNT(*) as total'))
            ->where('created_at', '>=', $depuis)
            ->groupBy('connected_app_id')
            ->orderByDesc('total')
            ->get()
            ->map(function ($row) {
                $app = $row->connected_app_id
                    ? ConnectedApp::find($row->connected_app_id)
                    : null;

                return (object) [
                    'connected_app_id' => $row->connected_app_id,
                    'code' => $app?->code ?? '— (sans app)',
                    'total' => $row->total,
                ];
            });
    }

    /** Agrège les logs du jour précédent dans api_metrics. */
    public function agregerJour(?Carbon $jour = null): int
    {
        $jour = ($jour ?? now()->subDay())->startOfDay();
        $fin = $jour->copy()->endOfDay();

        $lignes = ApiLog::query()
            ->select([
                'chemin',
                'connected_app_id',
                DB::raw('COUNT(*) as appels_total'),
                DB::raw("SUM(CASE WHEN statut_metier = 'succes' THEN 1 ELSE 0 END) as appels_succes"),
                DB::raw("SUM(CASE WHEN statut_metier = 'erreur' THEN 1 ELSE 0 END) as appels_erreur"),
                DB::raw("SUM(CASE WHEN statut_metier = 'rejet' THEN 1 ELSE 0 END) as appels_rejet"),
                DB::raw('AVG(duree_ms) as latence_moy'),
                DB::raw('MAX(duree_ms) as latence_max'),
            ])
            ->whereBetween('created_at', [$jour, $fin])
            ->groupBy('chemin', 'connected_app_id')
            ->get();

        $count = 0;
        foreach ($lignes as $l) {
            ApiMetric::updateOrCreate(
                [
                    'jour' => $jour->toDateString(),
                    'chemin' => $l->chemin,
                    'connected_app_id' => $l->connected_app_id,
                ],
                [
                    'appels_total' => $l->appels_total,
                    'appels_succes' => $l->appels_succes,
                    'appels_erreur' => $l->appels_erreur,
                    'appels_rejet' => $l->appels_rejet,
                    'latence_moy_ms' => (int) round((float) $l->latence_moy),
                    'latence_max_ms' => (int) $l->latence_max,
                ]
            );
            $count++;
        }

        return $count;
    }

    /** @return array<string, mixed> */
    public function rapportSecurite(): array
    {
        $depuis = now()->subDays(30);

        return [
            'genere_le' => now()->toIso8601String(),
            'periode_jours' => 30,
            'kpis' => $this->kpis($depuis),
            'rejets_rate_limit' => ApiLog::where('created_at', '>=', $depuis)->where('statut', 429)->count(),
            'apps_actives' => ConnectedApp::where('statut', 'actif')->count(),
            'webhooks_actifs' => \App\Models\Webhook::where('actif', true)->count(),
            'top_erreurs' => ApiLog::where('created_at', '>=', $depuis)
                ->where('statut_metier', 'erreur')
                ->select('chemin', DB::raw('COUNT(*) as total'))
                ->groupBy('chemin')
                ->orderByDesc('total')
                ->limit(10)
                ->get(),
        ];
    }
}
