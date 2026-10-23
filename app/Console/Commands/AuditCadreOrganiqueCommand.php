<?php

namespace App\Console\Commands;

use App\Models\Structure;
use App\Models\User;
use App\Services\Institution\AliasDemonstrationService;
use App\Services\Institution\CadreOrganiqueCeeacService;
use App\Services\Institution\InstitutionGouvernanceService;
use App\Services\Institution\StructureHierarchieService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class AuditCadreOrganiqueCommand extends Command
{
    protected $signature = 'institution:audit-cadre {--fix : Corriger les anomalies reparables automatiquement}';

    protected $description = 'Audite la coherence du cadre organique CEEAC et corrige les anomalies simples.';

    public function handle(
        CadreOrganiqueCeeacService $cadre,
        StructureHierarchieService $hierarchie,
        AliasDemonstrationService $aliasDemo,
        InstitutionGouvernanceService $gouvernance,
    ): int {
        if ($this->option('fix')) {
            $stats = $cadre->synchroniser();
            $this->info("Cadre synchronise: {$stats['crees']} crees, {$stats['mis_a_jour']} mis a jour, {$stats['renommes']} renommes.");

            $reorientation = $aliasDemo->reorienterDonneesMetier();
            if ($reorientation !== []) {
                $this->info('Donnees reorientees vers structures officielles: '.json_encode($reorientation));
            }
        }

        $anomalies = $this->collecterAnomalies($hierarchie, $aliasDemo, $gouvernance);

        if ($this->option('fix')) {
            $this->corriger($anomalies);
            $anomalies = $this->collecterAnomalies($hierarchie, $aliasDemo, $gouvernance);
        }

        foreach ($anomalies as $nom => $lignes) {
            $this->line($nom.': '.count($lignes));
            foreach (array_slice($lignes, 0, 10) as $ligne) {
                $this->line(' - '.$ligne);
            }
        }

        $total = array_sum(array_map('count', $anomalies));

        return $total === 0 ? self::SUCCESS : self::FAILURE;
    }

    /** @return array<string, list<string>> */
    private function collecterAnomalies(
        StructureHierarchieService $hierarchie,
        AliasDemonstrationService $aliasDemo,
        InstitutionGouvernanceService $gouvernance,
    ): array {
        return [
            'services_sans_direction' => $this->servicesSansDirection(),
            'directions_sans_departement' => $this->directionsSansDepartement(),
            'departements_sans_direction' => $hierarchie->departementsSansDirections(),
            'hierarchies_invalides' => $hierarchie->listerAnomalies(),
            'alias_demonstration' => $aliasDemo->listerAnomalies(),
            'gouvernance_institutionnelle' => $gouvernance->listerAnomalies(),
            'doublons_organisationnels' => $this->doublons(),
            'utilisateurs_orphelins' => $this->utilisateursOrphelins(),
            'responsables_invalides' => $this->responsablesInvalides(),
        ];
    }

    private function servicesSansDirection(): array
    {
        return Structure::query()
            ->leftJoin('structures as p', 'structures.parent_id', '=', 'p.id')
            ->where('structures.type', 'service')
            ->whereNull('structures.deleted_at')
            ->where(fn ($q) => $q->whereNull('p.id')->orWhere('p.type', '!=', 'direction'))
            ->orderBy('structures.code')
            ->get(['structures.code', 'structures.libelle'])
            ->map(fn ($row) => "{$row->code} - {$row->libelle}")
            ->all();
    }

    private function directionsSansDepartement(): array
    {
        return Structure::query()
            ->leftJoin('structures as p', 'structures.parent_id', '=', 'p.id')
            ->leftJoin('structures as p2', 'p.parent_id', '=', 'p2.id')
            ->leftJoin('structures as p3', 'p2.parent_id', '=', 'p3.id')
            ->where('structures.type', 'direction')
            ->whereNull('structures.deleted_at')
            ->whereRaw("COALESCE(p.type = 'departement', 0) + COALESCE(p2.type = 'departement', 0) + COALESCE(p3.type = 'departement', 0) = 0")
            ->orderBy('structures.code')
            ->get(['structures.code', 'structures.libelle'])
            ->map(fn ($row) => "{$row->code} - {$row->libelle}")
            ->all();
    }

    private function doublons(): array
    {
        return Structure::query()
            ->select('type', 'libelle', DB::raw('COUNT(*) as total'))
            ->whereNull('deleted_at')
            ->where('actif', true)
            ->groupBy('type', 'libelle')
            ->having('total', '>', 1)
            ->get()
            ->map(fn ($row) => "{$row->type}: {$row->libelle} ({$row->total})")
            ->all();
    }

    private function utilisateursOrphelins(): array
    {
        return User::query()
            ->leftJoin('structures as s', 'users.structure_id', '=', 's.id')
            ->whereNotNull('users.structure_id')
            ->whereNull('s.id')
            ->orderBy('users.id')
            ->get(['users.id', 'users.email'])
            ->map(fn ($row) => "{$row->id} - {$row->email}")
            ->all();
    }

    private function responsablesInvalides(): array
    {
        return Structure::query()
            ->leftJoin('users as u', 'structures.responsable_user_id', '=', 'u.id')
            ->whereNotNull('structures.responsable_user_id')
            ->whereNull('u.id')
            ->orderBy('structures.code')
            ->get(['structures.code', 'structures.libelle'])
            ->map(fn ($row) => "{$row->code} - {$row->libelle}")
            ->all();
    }

    private function corriger(array $anomalies): void
    {
        $dsg = Structure::where('code', 'DSG')->value('id');
        $dirPpb = Structure::where('code', 'DIR-PPB')->value('id');

        if ($dsg) {
            Structure::query()
                ->where('type', 'direction')
                ->whereNull('parent_id')
                ->update(['parent_id' => $dsg, 'niveau' => 3]);
        }

        if ($dirPpb) {
            Structure::query()
                ->where('type', 'service')
                ->whereNull('parent_id')
                ->update(['parent_id' => $dirPpb, 'niveau' => 4]);
        }

        Structure::query()
            ->whereNotNull('responsable_user_id')
            ->whereNotIn('responsable_user_id', User::query()->select('id'))
            ->update(['responsable_user_id' => null]);

        User::query()
            ->whereNotNull('structure_id')
            ->whereNotIn('structure_id', Structure::query()->select('id'))
            ->update(['structure_id' => null]);
    }
}
