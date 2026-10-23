<?php

namespace App\Services\Gouvernance;

use App\Models\ConstatAudit;
use App\Models\ControleInterne;
use App\Models\JournalAudit;
use App\Models\LoginLog;
use App\Models\MissionAudit;
use App\Models\NonConformite;
use App\Services\Budget\BudgetAuditService;
use App\Services\Depense\DepenseAuditService;
use App\Services\Pap\PapAuditService;
use App\Services\Rbm\RbmAuditService;
use App\Services\Risque\RisqueService;
use App\Services\Suivi\SuiviAuditService;
use Throwable;

/**
 * Cockpit de gouvernance (Lot D) : agrège, en lecture seule, les 8 services d'audit de conformité
 * existants + le registre des risques + les indicateurs de la piste d'audit. Chaque appel d'audit
 * est protégé (try/catch) afin que le cockpit reste robuste sur des données partielles.
 */
class GouvernanceService
{
    public function __construct(private RisqueService $risques)
    {
    }

    /** Conformité par domaine : score (si disponible) + anomalies + anomalies critiques. */
    public function conformiteParDomaine(int $exerciceId): array
    {
        return [
            $this->domaine('Chaîne de la dépense', fn () => app(DepenseAuditService::class)->executer($exerciceId, null, false)),
            $this->domaine('Suivi physique', fn () => app(SuiviAuditService::class)->executer($exerciceId, null, false)),
            $this->domaine('Budget & nomenclature', fn () => app(BudgetAuditService::class)->executer($exerciceId)),
            $this->domaine('PAP / GAR', fn () => $exerciceId ? app(PapAuditService::class)->executer($exerciceId) : []),
            $this->domaine('Chaîne RBM', fn () => app(RbmAuditService::class)->executer($exerciceId)),
        ];
    }

    /** Score global = moyenne des scores de conformité disponibles (0 si aucun). */
    public function scoreGlobal(array $conformite): int
    {
        $scores = array_filter(array_column($conformite, 'score'), fn ($s) => $s !== null);

        return $scores === [] ? 0 : (int) round(array_sum($scores) / count($scores));
    }

    /** Synthèse du registre des risques (central + agrégat). */
    public function syntheseRisques(): array
    {
        $stats = $this->risques->statistiques();
        $carto = $this->risques->cartographie(true);

        return [
            'total' => $carto['total'],
            'central' => $stats['central'],
            'par_niveau' => $carto['par_niveau'],
        ];
    }

    /** Synthèse des missions d'audit (INTOSAI). */
    public function missionsAudit(): array
    {
        return [
            'total' => MissionAudit::count(),
            'ouvertes' => MissionAudit::ouverte()->count(),
            'cloturees' => MissionAudit::where('statut', 'cloturee')->count(),
            'recommandations_en_attente' => ConstatAudit::whereIn('statut', ['ouvert', 'en_cours'])->count(),
        ];
    }

    /** Contrôle interne COSO : couverture (contrôles par composante) + efficacité. */
    public function controleInterne(): array
    {
        return [
            'total' => ControleInterne::count(),
            'composantes_couvertes' => ControleInterne::distinct()->count('composante'),
            'efficaces' => ControleInterne::where('evaluation', 'efficace')->count(),
            'inefficaces' => ControleInterne::where('evaluation', 'inefficace')->count(),
            'non_evalues' => ControleInterne::where('evaluation', 'non_evalue')->count(),
        ];
    }

    /** Conformité : taux de résolution des non-conformités + écarts ouverts. */
    public function conformite(): array
    {
        $total = NonConformite::count();
        $resolues = NonConformite::whereIn('statut', ['resolue', 'cloturee'])->count();

        return [
            'total' => $total,
            'ouvertes' => NonConformite::ouverte()->count(),
            'critiques_ouvertes' => NonConformite::where('gravite', 'critique')->ouverte()->count(),
            'taux_conformite' => $total > 0 ? round($resolues / $total * 100, 1) : 100.0,
        ];
    }

    /** Indicateurs de la piste d'audit (événements, violations SoD, connexions). */
    public function pisteAudit(): array
    {
        return [
            'evenements' => JournalAudit::count(),
            'sod_violations' => JournalAudit::where('action', 'sod_violation')->count(),
            'par_action' => JournalAudit::selectRaw('action, COUNT(*) n')->groupBy('action')
                ->orderByDesc('n')->limit(8)->pluck('n', 'action')->all(),
            'derniers' => JournalAudit::latest('created_at')->limit(8)->get(['user_nom', 'role', 'action', 'auditable_type', 'created_at']),
            'connexions_ok' => LoginLog::where('succes', true)->count(),
            'connexions_ko' => LoginLog::where('succes', false)->count(),
        ];
    }

    /** Normalise le rapport d'un service d'audit en {domaine, score, anomalies, critiques}. */
    private function domaine(string $libelle, callable $audit): array
    {
        try {
            $r = $audit();
        } catch (Throwable) {
            return ['domaine' => $libelle, 'score' => null, 'anomalies' => null, 'critiques' => null, 'erreur' => true];
        }

        return [
            'domaine' => $libelle,
            'score' => isset($r['score_conformite']) ? (int) $r['score_conformite'] : null,
            'anomalies' => (int) ($r['anomalies_total'] ?? 0),
            'critiques' => isset($r['anomalies_critiques']) ? (int) $r['anomalies_critiques'] : ($r['par_gravite']['elevee'] ?? null),
            'erreur' => false,
        ];
    }
}
