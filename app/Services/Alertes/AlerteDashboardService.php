<?php

namespace App\Services\Alertes;

use App\Models\Alerte;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/** Tableau de bord alertes institutionnelles (N-P1). */
class AlerteDashboardService
{
    /** @return array<string, mixed> */
    public function tableau(?string $module = null, ?string $statut = null): array
    {
        $base = Alerte::query()
            ->when($module, fn ($q) => $q->where('module', $module))
            ->when($statut, fn ($q) => $q->where('statut', $statut));

        return [
            'kpi' => [
                'ouvertes' => (clone $base)->where('statut', 'ouverte')->count(),
                'critiques' => Alerte::where('statut', 'ouverte')->whereIn('criticite', ['critique', 'urgence'])->count(),
                'resolues_30j' => Alerte::where('statut', 'resolue')->where('resolu_le', '>=', now()->subDays(30))->count(),
                'aujourdhui' => Alerte::whereDate('detecte_le', today())->count(),
            ],
            'par_module' => Alerte::query()
                ->select('module', DB::raw('COUNT(*) as total'), DB::raw("SUM(CASE WHEN statut = 'ouverte' THEN 1 ELSE 0 END) as ouvertes"))
                ->groupBy('module')
                ->orderByDesc('ouvertes')
                ->get(),
            'recentes' => Alerte::query()
                ->with('structure')
                ->when($module, fn ($q) => $q->where('module', $module))
                ->when($statut, fn ($q) => $q->where('statut', $statut))
                ->latest('detecte_le')
                ->limit(50)
                ->get(),
        ];
    }

    /** @return Collection<int, array<string, mixed>> */
    public function exportCsvLignes(): Collection
    {
        return Alerte::with('structure')->orderByDesc('detecte_le')->get()->map(fn (Alerte $a) => [
            $a->reference,
            $a->module,
            $a->code,
            $a->structure?->code,
            $a->structure?->sigle ?: $a->structure?->libelle,
            $a->criticite,
            $a->statut,
            $a->titre,
            $a->detecte_le?->format('Y-m-d H:i'),
            $a->resolu_le?->format('Y-m-d H:i'),
        ]);
    }
}
