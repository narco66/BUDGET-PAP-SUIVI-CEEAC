<?php

namespace App\Services\Suivi;

use App\Models\CampagneSuivi;
use App\Models\Collecte;
use App\Models\EvaluationPeriodique;
use App\Models\Exercice;
use App\Models\Formation;
use App\Models\MissionSuivi;
use App\Models\PlanAmelioration;
use App\Models\Recommandation;
use App\Models\User;
use App\Services\Rbm\IndicateurPerformanceService;
use App\Support\SuiviEvaluationPermissionAliases;
use Illuminate\Support\Collection;
use Spatie\Permission\Exceptions\PermissionDoesNotExist;

/** Orchestration du module Suivi-Évaluation (phases P0 → P3). */
class SuiviEvaluationService
{
    public function __construct(
        private readonly SuiviAuditService $audit,
        private readonly IndicateurPerformanceService $indicateurs,
    ) {
    }

    /** @return array<string, mixed> */
    public function hub(int $exerciceId): array
    {
        $scores = $this->composantesExercice($exerciceId);
        $audit = $this->audit->executer($exerciceId);
        $feux = $this->indicateurs->feuxGlobaux($exerciceId);

        return [
            'scores' => $scores,
            'niveau' => SuiviEvaluationCalculator::niveauPerformance($scores['score_global']),
            'feux' => $feux,
            'audit' => $audit,
            'campagnes' => CampagneSuivi::where('exercice_id', $exerciceId)->orderByDesc('date_debut')->get(),
            'recommandations' => $this->statsRecommandations($exerciceId),
            'formations' => $this->statsFormations($exerciceId),
            'evaluations' => $this->historiqueExercice($exerciceId, 10),
            'evolution' => $this->evolutionScores($exerciceId),
            'missions' => $this->statsMissions($exerciceId),
            'plans' => $this->statsPlans($exerciceId),
        ];
    }

    /** @return array<string, mixed> */
    public function composantesExercice(int $exerciceId, ?int $campagneId = null): array
    {
        $audit = $this->audit->executer($exerciceId);
        $synthese = $audit['synthese'];
        $tauxCouverture = (float) ($synthese['taux_couverture'] ?? 0);

        $collecteQuery = Collecte::query()
            ->where('statut', 'validee')
            ->whereHas('campagne', fn ($q) => $q->where('exercice_id', $exerciceId));

        if ($campagneId) {
            $collecteQuery->where('campagne_id', $campagneId);
        }

        $tauxMoyen = $collecteQuery->avg('taux_physique');
        $feux = $this->indicateurs->feuxGlobaux($exerciceId);
        $recStats = $this->statsRecommandations($exerciceId);
        $formStats = $this->statsFormations($exerciceId);

        return SuiviEvaluationCalculator::synthese([
            'physique' => SuiviEvaluationCalculator::scorePhysique(
                $tauxCouverture,
                $tauxMoyen !== null ? (float) $tauxMoyen : null,
            ),
            'iov' => SuiviEvaluationCalculator::scoreIov(
                (int) ($feux['vert'] ?? 0),
                (int) ($feux['orange'] ?? 0),
                (int) ($feux['rouge'] ?? 0),
            ),
            'recommandations' => SuiviEvaluationCalculator::scoreRecommandations(
                (float) ($recStats['taux_execution_moyen'] ?? 0),
                (float) ($recStats['taux_realisees'] ?? 0),
            ),
            'formations' => SuiviEvaluationCalculator::scoreFormations(
                (float) ($formStats['taux_realisation'] ?? 0),
                $formStats['satisfaction_moyenne'] !== null ? (float) $formStats['satisfaction_moyenne'] : null,
            ),
            'conformite' => (float) ($audit['score_conformite'] ?? 0),
        ]);
    }

    public function genererEvaluation(int $exerciceId, ?int $campagneId = null, ?int $userId = null): EvaluationPeriodique
    {
        $campagne = $campagneId ? CampagneSuivi::find($campagneId) : null;
        $exercice = Exercice::find($exerciceId);
        $type = $campagne ? 'campagne' : 'annuelle';
        $periode = $campagne?->periode ?? ('AN-'.($exercice?->annee ?? now()->year));

        return $this->persister($exerciceId, $type, $periode, $campagneId, $userId);
    }

    /**
     * Évaluation d'impact (fin de cycle) : snapshot S&E typé « impact », distinct des évaluations
     * périodiques/annuelles. Réutilise le même socle de scoring que les autres évaluations.
     */
    public function genererEvaluationImpact(int $exerciceId, ?int $userId = null): EvaluationPeriodique
    {
        $exercice = Exercice::find($exerciceId);
        $periode = 'IMP-'.($exercice?->annee ?? now()->year);

        return $this->persister($exerciceId, 'impact', $periode, null, $userId);
    }

    private function persister(int $exerciceId, string $type, string $periode, ?int $campagneId, ?int $userId): EvaluationPeriodique
    {
        $scores = $this->composantesExercice($exerciceId, $campagneId);

        return EvaluationPeriodique::updateOrCreate(
            [
                'exercice_id' => $exerciceId,
                'periode' => $periode,
                'type' => $type,
            ],
            [
                'campagne_id' => $campagneId,
                'score_physique' => $scores['score_physique'],
                'score_iov' => $scores['score_iov'],
                'score_recommandations' => $scores['score_recommandations'],
                'score_formations' => $scores['score_formations'],
                'score_conformite' => $scores['score_conformite'],
                'score_global' => $scores['score_global'],
                'niveau' => SuiviEvaluationCalculator::niveauPerformance($scores['score_global']),
                'details' => ['composantes' => $scores, 'poids' => SuiviEvaluationCalculator::POIDS_COMPOSANTES],
                'genere_par' => $userId,
                'genere_le' => now(),
            ],
        );
    }

    /** Capture batch : évaluation annuelle + chaque campagne clôturée (P1). */
    public function capturerExercice(int $exerciceId, ?int $userId = null): int
    {
        $n = 0;
        $this->genererEvaluation($exerciceId, null, $userId);
        $n++;

        CampagneSuivi::where('exercice_id', $exerciceId)
            ->where('statut', 'cloturee')
            ->each(function (CampagneSuivi $c) use ($exerciceId, $userId, &$n) {
                $this->genererEvaluation($exerciceId, $c->id, $userId);
                $n++;
            });

        return $n;
    }

    public function resoudreExerciceId(mixed $opt): ?int
    {
        if ($opt === null || $opt === '') {
            return Exercice::firstWhere('statut', 'ouvert')?->id ?? Exercice::orderByDesc('annee')->value('id');
        }

        return Exercice::where('id', $opt)->orWhere('annee', $opt)->value('id');
    }

    /** @return Collection<int, EvaluationPeriodique> */
    public function historiqueExercice(int $exerciceId, ?int $limit = null): Collection
    {
        $q = EvaluationPeriodique::with(['campagne', 'generePar'])
            ->where('exercice_id', $exerciceId)
            ->orderByDesc('genere_le');

        return $limit ? $q->limit($limit)->get() : $q->get();
    }

    /** @return list<array<string, mixed>> */
    public function evolutionScores(int $exerciceId): array
    {
        return $this->historiqueExercice($exerciceId)
            ->sortBy('genere_le')
            ->map(fn (EvaluationPeriodique $e) => [
                'periode' => $e->periode,
                'type' => $e->type,
                'score_global' => (float) $e->score_global,
                'niveau' => $e->niveau,
                'genere_le' => $e->genere_le?->format('d/m/Y'),
            ])
            ->values()
            ->all();
    }

    /** @return array<string, mixed> */
    public function rapportDonnees(int $exerciceId): array
    {
        $scores = $this->composantesExercice($exerciceId);
        $historique = $this->historiqueExercice($exerciceId);
        $derniere = $historique->first();

        return [
            'scores_courants' => $scores,
            'niveau' => SuiviEvaluationCalculator::niveauPerformance($scores['score_global']),
            'historique' => $historique->map(fn (EvaluationPeriodique $e) => [
                'periode' => $e->periode,
                'type' => $e->type,
                'score_global' => (float) $e->score_global,
                'score_physique' => (float) $e->score_physique,
                'score_iov' => (float) $e->score_iov,
                'score_recommandations' => (float) $e->score_recommandations,
                'score_formations' => (float) $e->score_formations,
                'score_conformite' => (float) $e->score_conformite,
                'niveau' => EvaluationPeriodique::NIVEAUX[$e->niveau] ?? $e->niveau,
                'genere_le' => $e->genere_le?->format('d/m/Y H:i') ?? '—',
            ]),
            'recommandations' => $this->statsRecommandations($exerciceId),
            'formations' => $this->statsFormations($exerciceId),
            'missions' => $this->statsMissions($exerciceId),
            'plans' => $this->statsPlans($exerciceId),
            'derniere_evaluation_id' => $derniere?->id,
        ];
    }

    /** @return array<string, int|float> */
    private function statsMissions(int $exerciceId): array
    {
        $base = MissionSuivi::where('exercice_id', $exerciceId);

        return [
            'total' => $base->count(),
            'en_cours' => (clone $base)->where('statut', 'en_cours')->count(),
            'cloturees' => (clone $base)->where('statut', 'cloturee')->count(),
        ];
    }

    /** @return array<string, int|float> */
    private function statsPlans(int $exerciceId): array
    {
        $base = PlanAmelioration::where('exercice_id', $exerciceId);

        return [
            'total' => $base->count(),
            'en_cours' => (clone $base)->whereIn('statut', ['valide', 'en_cours'])->count(),
            'taux_moyen' => round((float) ((clone $base)->avg('taux_realisation') ?? 0), 1),
        ];
    }

    /** @return array<string, int|float|null> */
    private function statsRecommandations(int $exerciceId): array
    {
        // Un seul agrégat (mêmes définitions que les comptages séparés : « actives » = statut différent de abandonnee).
        $r = Recommandation::where('exercice_id', $exerciceId)->toBase()->selectRaw(
            "sum(case when statut <> 'abandonnee' then 1 else 0 end) as total, "
            ."sum(case when statut = 'realisee' then 1 else 0 end) as realisees, "
            ."sum(case when statut = 'en_cours' then 1 else 0 end) as en_cours, "
            ."avg(case when statut <> 'abandonnee' then taux_execution end) as taux_moyen"
        )->first();
        $total = (int) ($r->total ?? 0);
        $realisees = (int) ($r->realisees ?? 0);

        return [
            'total' => $total,
            'realisees' => $realisees,
            'en_cours' => (int) ($r->en_cours ?? 0),
            'taux_execution_moyen' => round((float) ($r->taux_moyen ?? 0), 1),
            'taux_realisees' => SuiviEvaluationCalculator::taux($realisees, max(1, $total)),
        ];
    }

    /** @return array<string, int|float|null> */
    private function statsFormations(int $exerciceId): array
    {
        $r = Formation::where('exercice_id', $exerciceId)->toBase()->selectRaw(
            'count(*) as total, '
            ."sum(case when statut = 'realise' then 1 else 0 end) as realisees, "
            ."avg(case when statut = 'realise' then niveau_satisfaction end) as satisfaction"
        )->first();
        $total = (int) ($r->total ?? 0);
        $realisees = (int) ($r->realisees ?? 0);

        return [
            'total' => $total,
            'realisees' => $realisees,
            'taux_realisation' => SuiviEvaluationCalculator::taux($realisees, max(1, $total)),
            'satisfaction_moyenne' => $r->satisfaction !== null ? (float) $r->satisfaction : null,
        ];
    }

    public function peutConsulter(?User $user): bool
    {
        return $user && (
            $this->perm($user, 'suivi_evaluation.view')
            || $this->perm($user, 'suivi.voir')
            || $this->perm($user, 'recommandation.voir')
            || $this->perm($user, 'rapport.voir')
        );
    }

    public function peutGenerer(?User $user): bool
    {
        return $user && (
            $this->perm($user, 'suivi_evaluation.validate')
            || $this->perm($user, 'suivi_evaluation.admin')
            || $this->perm($user, 'suivi.valider')
        );
    }

    public function peutExporter(?User $user): bool
    {
        return $user && (
            $this->perm($user, 'suivi_evaluation.export')
            || $this->perm($user, 'suivi.exporter')
            || $this->perm($user, 'rapport.exporter')
        );
    }

    public function peutCreer(?User $user): bool
    {
        return $user && (
            $this->perm($user, 'suivi_evaluation.create')
            || $this->perm($user, 'suivi.creer')
            || $this->perm($user, 'suivi_evaluation.admin')
        );
    }

    public function peutRecommander(?User $user): bool
    {
        return $user && (
            $this->perm($user, 'suivi_evaluation.recommend')
            || $this->perm($user, 'recommandation.creer')
        );
    }

    private function perm(User $user, string $permission): bool
    {
        $alias = SuiviEvaluationPermissionAliases::evaluate($user, $permission);
        if ($alias !== null) {
            return $alias;
        }

        try {
            return $user->hasPermissionTo($permission, 'web');
        } catch (PermissionDoesNotExist) {
            return false;
        }
    }
}
