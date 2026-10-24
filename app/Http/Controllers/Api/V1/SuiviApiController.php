<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Exercice;
use App\Services\Budget\CompteurMouvementAuditService;
use App\Services\Budget\SituationBudgetaireService;
use App\Services\Budget\SuiviFinancierService;
use App\Services\Pap\PapPerformanceService;
use App\Services\Rbm\IndicateurPerformanceService;
use App\Services\Suivi\SuiviEvaluationService;
use Illuminate\Http\JsonResponse;
use Spatie\Permission\Exceptions\PermissionDoesNotExist;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** Endpoints REST Suivi Financier, Indicateurs & Suivi-Évaluation (phase P3). */
class SuiviApiController extends Controller
{
    public function __construct(
        private readonly SuiviFinancierService $financier,
        private readonly SituationBudgetaireService $situations,
        private readonly PapPerformanceService $pap,
        private readonly IndicateurPerformanceService $indicateurs,
        private readonly SuiviEvaluationService $suiviEvaluation,
    ) {
    }

    public function syntheseFinanciere(Request $request): JsonResource
    {
        $this->assertFinancier($request);
        $exerciceId = $this->exerciceIdRequis($request);

        $hub = $this->financier->hub($exerciceId);

        return new JsonResource([
            'exercice_id' => $exerciceId,
            'budget' => $hub['budget'],
            'par_structure' => $hub['parStructure'],
            'ecarts' => collect($hub['ecarts'])->take(20)->values(),
            'par_tache' => collect($hub['parTache'])->values(),
            'anomalies_total' => $hub['anomalies_total'],
            'peut_capturer' => $this->peutCapturerSansException($request),
        ]);
    }

    public function anomaliesFinancieres(Request $request, CompteurMouvementAuditService $audit): JsonResource
    {
        $this->assertFinancier($request);
        $exerciceId = $this->exerciceIdRequis($request);

        return new JsonResource([
            'exercice_id' => $exerciceId,
            'audit' => $audit->analyser($exerciceId),
        ]);
    }

    public function capturerSituation(Request $request): JsonResponse
    {
        $this->assertFinancier($request);
        abort_unless($this->peutCapturerSansException($request), 403);
        $exerciceId = $this->exerciceIdRequis($request);
        $this->situations->capturer($exerciceId, $request->user()->id);

        return response()->json(['message' => 'Situation budgétaire capturée.', 'code' => 'capturee']);
    }

    public function ecartsPhysiqueFinancier(Request $request): JsonResource
    {
        $this->assertFinancier($request);
        $exerciceId = $this->exerciceIdRequis($request);

        return JsonResource::collection(
            $this->pap->ecartsPhysiqueFinancier($exerciceId)->values()
        );
    }

    public function situationsBudgetaires(Request $request): JsonResource
    {
        $this->assertFinancier($request);
        $exerciceId = $this->exerciceIdRequis($request);
        $limit = min(100, max(1, (int) $request->integer('limit', 30)));

        return JsonResource::collection(
            $this->situations->historique($exerciceId, $limit)
        );
    }

    public function restesAEngager(Request $request): JsonResource
    {
        $this->assertFinancier($request);
        $exerciceId = $this->exerciceIdRequis($request);

        return JsonResource::collection(
            $this->situations->restesAEngager($exerciceId)->values()
        );
    }

    public function feuxIndicateurs(Request $request): JsonResource
    {
        $this->assertIndicateurs($request);
        $exerciceId = $this->exerciceIdRequis($request);

        return new JsonResource([
            'exercice_id' => $exerciceId,
            'feux' => $this->indicateurs->feuxGlobaux($exerciceId),
        ]);
    }

    public function evolutionIndicateurs(Request $request): JsonResource
    {
        $this->assertIndicateurs($request);
        $exerciceId = $this->exerciceIdRequis($request);

        return JsonResource::collection(
            $this->indicateurs->evolutionExercice($exerciceId)->values()
        );
    }

    public function performancesIndicateurs(Request $request): JsonResource
    {
        $this->assertIndicateurs($request);
        $exerciceId = $this->exerciceIdRequis($request);

        return JsonResource::collection(
            $this->indicateurs->performancesExercice($exerciceId)->map(fn ($p) => [
                'id' => $p->id,
                'indicateur_id' => $p->indicateur_id,
                'code' => $p->indicateur?->code,
                'libelle' => $p->indicateur?->libelle,
                'periode' => $p->periode,
                'valeur_cible' => (float) $p->valeur_cible,
                'valeur_realisee' => (float) $p->valeur_realisee,
                'taux_atteinte' => (float) $p->taux_atteinte,
                'feu' => $p->feu,
                'capture_le' => $p->capture_le?->toIso8601String(),
            ])->values()
        );
    }

    public function syntheseEvaluation(Request $request): JsonResource
    {
        $this->assertEvaluation($request);
        $exerciceId = $this->exerciceIdRequis($request);
        $hub = $this->suiviEvaluation->hub($exerciceId);

        return new JsonResource([
            'exercice_id' => $exerciceId,
            'scores' => $hub['scores'],
            'niveau' => $hub['niveau'],
            'feux' => $hub['feux'],
            'audit' => [
                'anomalies_total' => $hub['audit']['anomalies_total'] ?? count($hub['audit']['anomalies'] ?? []),
            ],
            'recommandations' => $hub['recommandations'],
            'formations' => $hub['formations'],
            'missions' => $hub['missions'],
            'plans' => $hub['plans'],
            'peut_generer' => $this->suiviEvaluation->peutGenerer($request->user()),
        ]);
    }

    public function genererEvaluation(Request $request): JsonResponse
    {
        $this->assertEvaluation($request);
        abort_unless($this->suiviEvaluation->peutGenerer($request->user()), 403);
        $exerciceId = $this->exerciceIdRequis($request);
        $campagneId = $request->filled('campagne') ? $request->integer('campagne') : null;
        $this->suiviEvaluation->genererEvaluation($exerciceId, $campagneId, $request->user()->id);

        return response()->json(['message' => 'Évaluation périodique générée.', 'code' => 'generee']);
    }

    public function evaluationsPeriodiques(Request $request): JsonResource
    {
        $this->assertEvaluation($request);
        $exerciceId = $this->exerciceIdRequis($request);

        return JsonResource::collection(
            $this->suiviEvaluation->historiqueExercice($exerciceId)->map(fn ($e) => [
                'id' => $e->id,
                'periode' => $e->periode,
                'type' => $e->type,
                'score_global' => (float) $e->score_global,
                'niveau' => $e->niveau,
                'genere_le' => $e->genere_le?->toIso8601String(),
            ])->values()
        );
    }

    public function missionsSuivi(Request $request): JsonResource
    {
        $this->assertEvaluation($request);
        $exerciceId = $this->exerciceIdRequis($request);

        return JsonResource::collection(
            \App\Models\MissionSuivi::where('exercice_id', $exerciceId)
                ->orderByDesc('id')
                ->get(['id', 'numero', 'type', 'libelle', 'statut', 'score_mission', 'date_debut', 'date_fin'])
        );
    }

    public function plansAmelioration(Request $request): JsonResource
    {
        $this->assertEvaluation($request);
        $exerciceId = $this->exerciceIdRequis($request);

        return JsonResource::collection(
            \App\Models\PlanAmelioration::withCount('actions')
                ->where('exercice_id', $exerciceId)
                ->orderByDesc('id')
                ->get()
                ->map(fn ($p) => [
                    'id' => $p->id,
                    'numero' => $p->numero,
                    'intitule' => $p->intitule,
                    'statut' => $p->statut,
                    'taux_realisation' => (float) $p->taux_realisation,
                    'actions_count' => $p->actions_count,
                ])
        );
    }

    private function assertEvaluation(Request $request): void
    {
        abort_unless($this->tokenAutorise($request), 403, 'Scope API insuffisant (lecture ou suivi requis).');
        abort_unless($this->suiviEvaluation->peutConsulter($request->user()), 403);
    }

    private function assertFinancier(Request $request): void
    {
        abort_unless($this->tokenAutorise($request), 403, 'Scope API insuffisant (lecture ou suivi requis).');
        abort_unless($this->financier->peutConsulter($request->user()), 403);
    }

    private function assertIndicateurs(Request $request): void
    {
        abort_unless($this->tokenAutorise($request), 403, 'Scope API insuffisant (lecture ou suivi requis).');
        abort_unless(
            $request->user()->hasPermissionTo('indicateur.voir', 'web')
            || $request->user()->hasPermissionTo('suivi_financier.view', 'web'),
            403
        );
    }

    private function peutCapturerSansException(Request $request): bool
    {
        try {
            return $this->financier->peutCapturer($request->user());
        } catch (PermissionDoesNotExist) {
            return false;
        }
    }

    private function tokenAutorise(Request $request): bool
    {
        $user = $request->user();
        if (! $user) {
            return false;
        }

        if (! $user->currentAccessToken()) {
            return true;
        }

        return $user->tokenCan('lecture') || $user->tokenCan('suivi') || $user->tokenCan('*');
    }

    private function exerciceIdRequis(Request $request): int
    {
        $id = $this->exerciceId($request);
        abort_unless($id, 422, 'Paramètre exercice requis (ID ou année).');

        return $id;
    }

    private function exerciceId(Request $request): ?int
    {
        if (! $request->filled('exercice')) {
            return null;
        }

        $opt = $request->input('exercice');

        return Exercice::where('id', $opt)->orWhere('annee', $opt)->value('id');
    }
}
