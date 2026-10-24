<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\LigneBudgetaireRequest;
use App\Http\Resources\BudgetResource;
use App\Models\Exercice;
use App\Models\LigneBudgetaire;
use App\Services\Api\IdempotenceActionService;
use App\Services\Budget\LigneCreditsService;
use App\Support\Montant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

/** Lignes de crédits (Budget et crédits) — session SPA. Import/export restent Blade. */
class BudgetCreditsApiController extends Controller
{
    public function __construct(
        private readonly LigneCreditsService $credits,
        private readonly IdempotenceActionService $idempotence,
    ) {}

    public function index(Request $request): JsonResponse
    {
        abort_unless($request->user()?->can('ligne_budgetaire.voir'), 403);

        $exercice = $this->credits->exercice($request->filled('exercice') ? $request->integer('exercice') : null);
        $base = $this->credits->requete($request->user(), $exercice?->id, $request);
        $lignes = (clone $base)
            ->with('rubrique', 'structure', 'sourceFinancement')
            ->orderBy('code')
            ->paginate(min(100, max(1, (int) $request->integer('per_page', 25))));
        $totaux = $this->credits->totaux($base);
        $enveloppe = $this->credits->enveloppe($exercice?->id);
        $user = $request->user();
        $verrouillees = $this->credits->lignesVerrouillees($exercice?->id);

        return response()->json([
            'exercice' => $exercice?->only(['id', 'annee', 'libelle', 'statut']),
            'enveloppe' => $enveloppe
                ? (new BudgetResource($enveloppe->loadMissing('exercice')))->resolve($request)
                : null,
            'lignes_verrouillees' => $verrouillees,
            'totaux' => [
                'ouvert' => Montant::api($totaux->o ?? 0),
                'engage' => Montant::api($totaux->e ?? 0),
                'liquide' => Montant::api($totaux->l ?? 0),
                'ordonnance' => Montant::api($totaux->ord ?? 0),
                'paye' => Montant::api($totaux->p ?? 0),
                'disponible' => Montant::api(((float) ($totaux->o ?? 0)) - ((float) ($totaux->e ?? 0))),
            ],
            'par_nature' => $this->credits->syntheseParNature($exercice?->id)->map(fn ($n) => [
                'code' => $n->code,
                'intitule' => $n->intitule,
                'sens' => $n->sens,
                'ouvert' => Montant::api($n->ouvert),
                'engage' => Montant::api($n->engage),
            ])->values(),
            'par_structure' => $this->credits->syntheseParStructure($exercice?->id)->take(20)->map(fn ($s) => [
                'libelle' => $s->libelle,
                'ouvert' => Montant::api($s->ouvert),
                'engage' => Montant::api($s->engage),
            ])->values(),
            'lignes' => collect($lignes->items())->map(fn (LigneBudgetaire $l) => $this->serialiser($l, $user))->values(),
            'meta' => [
                'total' => $lignes->total(),
                'page' => $lignes->currentPage(),
                'per_page' => $lignes->perPage(),
            ],
            'actions' => [
                'creer' => ($user?->can('create', LigneBudgetaire::class) ?? false) && ! $verrouillees,
                'exporter' => $user?->can('ligne_budgetaire.exporter') ?? false,
            ],
            'exports' => $this->exports($exercice?->id, (bool) ($user?->can('ligne_budgetaire.exporter'))),
        ]);
    }

    public function options(Request $request): JsonResponse
    {
        abort_unless($request->user()?->can('ligne_budgetaire.voir'), 403);

        $exercice = $this->credits->exercice($request->filled('exercice') ? $request->integer('exercice') : null);
        $opts = $this->credits->options($exercice);

        return response()->json([
            'exercice' => $exercice?->only(['id', 'annee', 'libelle', 'statut']),
            'exercices' => Exercice::orderByDesc('annee')->get(['id', 'annee', 'libelle', 'statut']),
            'rubriques' => $opts['rubriques'],
            'structures' => $opts['structures'],
            'sources' => $opts['sources'],
            'activites' => $opts['activites'],
            'lignes_verrouillees' => $this->credits->lignesVerrouillees($exercice?->id),
        ]);
    }

    public function show(Request $request, LigneBudgetaire $ligne): JsonResponse
    {
        $this->authorize('view', $ligne);
        $ligne->load(['rubrique', 'structure', 'sourceFinancement', 'exercice']);

        return response()->json([
            'ligne' => $this->serialiser($ligne, $request->user()),
            'financement' => collect($this->credits->financementPourFormulaire($ligne))->map(fn ($s) => [
                'source_financement_id' => $s['source_financement_id'],
                'montant' => Montant::api($s['montant']),
                'est_principale' => (bool) ($s['est_principale'] ?? false),
            ])->values(),
        ]);
    }

    public function store(LigneBudgetaireRequest $request): JsonResponse
    {
        $this->authorize('create', LigneBudgetaire::class);
        $data = $request->validated();
        $resultat = $this->idempotence->executer(
            $request,
            'credits.creer.'.$data['exercice_id'].'.'.$data['code'],
            function () use ($request, $data) {
                try {
                    $ligne = $this->credits->creer($data, $request);
                } catch (RuntimeException $e) {
                    return ['status' => 422, 'body' => ['message' => $e->getMessage(), 'code' => 'transition_invalide']];
                }

                return [
                    'status' => 201,
                    'body' => [
                        'message' => 'Ligne budgétaire créée.',
                        'ligne' => $this->serialiser($ligne, $request->user()),
                    ],
                ];
            }
        );

        return response()->json($resultat['body'], $resultat['status']);
    }

    public function update(LigneBudgetaireRequest $request, LigneBudgetaire $ligne): JsonResponse
    {
        $this->authorize('update', $ligne);
        $this->assertVersion($request, $ligne);
        try {
            $ligne = $this->credits->mettreAJour($ligne, $request->validated(), $request);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage(), 'code' => 'transition_invalide'], 422);
        }

        return response()->json([
            'message' => 'Ligne budgétaire mise à jour.',
            'ligne' => $this->serialiser($ligne, $request->user()),
        ]);
    }

    public function destroy(Request $request, LigneBudgetaire $ligne): JsonResponse
    {
        $this->authorize('delete', $ligne);
        $this->assertVersion($request, $ligne);
        try {
            $this->credits->supprimer($ligne);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage(), 'code' => 'transition_invalide'], 422);
        }

        return response()->json(['message' => 'Ligne budgétaire supprimée.']);
    }

    /** @return array<string, mixed> */
    private function serialiser(LigneBudgetaire $l, mixed $user): array
    {
        return [
            'id' => $l->id,
            'code' => $l->code,
            'intitule' => $l->intitule,
            'exercice_id' => $l->exercice_id,
            'rubrique_id' => $l->rubrique_id,
            'structure_id' => $l->structure_id,
            'source_financement_id' => $l->source_financement_id,
            'activite_id' => $l->activite_id,
            'rubrique' => $l->rubrique?->code,
            'structure' => $l->structure?->sigle ?: $l->structure?->libelle,
            'montant_ouvert' => Montant::api($l->montant_ouvert),
            'montant_revise' => $l->montant_revise !== null ? Montant::api($l->montant_revise) : null,
            'montant_alloue' => Montant::api($l->montant_alloue),
            'montant_engage' => Montant::api($l->montant_engage),
            'montant_liquide' => Montant::api($l->montant_liquide),
            'montant_ordonnance' => Montant::api($l->montant_ordonnance),
            'montant_paye' => Montant::api($l->montant_paye),
            'disponible' => Montant::api($l->disponible),
            'etag' => $l->updated_at?->toIso8601String(),
            'actions' => [
                'modifier' => $user?->can('update', $l) ?? false,
                'supprimer' => $user?->can('delete', $l) ?? false,
            ],
        ];
    }

    /** @return array<string, string> */
    private function exports(?int $exerciceId, bool $peutExporter): array
    {
        $legacy = rtrim((string) config('spa.legacy_url', config('app.url')), '/');
        $liens = [
            'import' => $legacy.route('budget.import.form', [], false),
        ];
        if ($peutExporter) {
            $liens['export'] = $legacy.route('budget.export', array_filter(['exercice' => $exerciceId]), false);
        }

        return $liens;
    }

    private function assertVersion(Request $request, LigneBudgetaire $ligne): void
    {
        $attendu = $request->header('If-Match');
        if ($attendu === null || $attendu === '') {
            return;
        }
        if ($attendu !== $ligne->updated_at?->toIso8601String()) {
            abort(response()->json([
                'message' => 'La ligne a été modifiée. Rechargez avant d’agir.',
                'code' => 'conflict',
            ], 409));
        }
    }
}
