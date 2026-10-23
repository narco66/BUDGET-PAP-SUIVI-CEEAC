<?php

namespace App\Services\Reporting;

use App\Services\AdministrationTechnique\SanteService;
use App\Services\Audit\AuditRetentionService;
use App\Services\Audit\DataIntegrityCheckService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Données des rapports techniques institutionnels (P3-2). */
class RapportTechniqueService
{
    public const TYPES = [
        'technique_sante' => 'Rapport technique — Santé plateforme',
        'technique_integrite' => 'Rapport technique — Intégrité des données',
        'technique_audit' => 'Rapport technique — Journaux d\'audit',
    ];

    public function __construct(
        private readonly SanteService $sante,
        private readonly DataIntegrityCheckService $integrite,
        private readonly AuditRetentionService $retention,
    ) {
    }

    /** @return array{statut_global: string, controles: array, mesures: array, jobs_echoues: \Illuminate\Support\Collection} */
    public function sante(): array
    {
        return [
            'statut_global' => $this->sante->statutGlobal(),
            'controles' => $this->sante->controles(),
            'mesures' => $this->sante->mesures(),
            'jobs_echoues' => $this->sante->jobsEchoues(10),
        ];
    }

    /** @return array<string, mixed> */
    public function integrite(int $exerciceId): array
    {
        return $this->integrite->executer($exerciceId, null, false);
    }

    /** @return array{tables: \Illuminate\Support\Collection, activite: \Illuminate\Support\Collection} */
    public function audit(): array
    {
        $tables = collect($this->retention->tablesConfigurées())
            ->map(function (int $jours, string $table) {
                if (! Schema::hasTable($table)) {
                    return null;
                }
                $oldest = DB::table($table)->min('created_at');

                return [
                    'table' => $table,
                    'retention_jours' => $jours,
                    'enregistrements' => (int) DB::table($table)->count(),
                    'plus_ancien' => $oldest ? Carbon::parse($oldest)->format('d/m/Y') : '—',
                ];
            })
            ->filter()
            ->values();

        $activite = Schema::hasTable('journaux_audit')
            ? DB::table('journaux_audit')
                ->select('action', DB::raw('count(*) as total'))
                ->where('created_at', '>=', now()->subDays(7))
                ->groupBy('action')
                ->orderByDesc('total')
                ->limit(15)
                ->get()
                ->map(fn ($r) => ['action' => $r->action, 'total' => (int) $r->total])
            : collect();

        return ['tables' => $tables, 'activite' => $activite];
    }
}
