<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\RapportPersonnaliseRequest;
use App\Models\Exercice;
use App\Models\Rapport;
use App\Models\RapportPersonnalise;
use App\Services\Api\IdempotenceActionService;
use App\Services\Reporting\RapportPersonnaliseService;
use App\Services\Reporting\RapportRenderer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RapportPersonnaliseApiController extends Controller
{
    public function __construct(
        private readonly RapportPersonnaliseService $svc,
        private readonly RapportRenderer $renderer,
        private readonly IdempotenceActionService $idempotence,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Rapport::class);
        $userId = $request->user()->id;
        $items = RapportPersonnalise::with('auteur')
            ->where('actif', true)
            ->where(function ($q) use ($userId) {
                $q->where('partage', true)->orWhere('cree_par', $userId);
            })
            ->latest('id')
            ->paginate(min(100, max(1, (int) $request->integer('per_page', 20))));

        return response()->json([
            'data' => $items->getCollection()->map(fn (RapportPersonnalise $m) => $this->resume($m, $request))->values(),
            'meta' => ['total' => $items->total(), 'current_page' => $items->currentPage()],
            'peut_creer' => (bool) $request->user()?->can('reporting.create'),
            'sources' => $this->sourcesLibelles(),
        ]);
    }

    public function options(Request $request): JsonResponse
    {
        abort_unless($request->user()?->can('reporting.create') || $request->user()?->can('reporting.view'), 403);

        return response()->json(['sources' => RapportPersonnaliseService::SOURCES]);
    }

    public function show(Request $request, RapportPersonnalise $rapportPersonnalise): JsonResponse
    {
        $this->authorizeModele($request, $rapportPersonnalise);
        $exercice = $this->exercice($request);
        $apercu = $this->svc->composer($rapportPersonnalise, $exercice->id, [
            'exercice_annee' => $exercice->annee,
            'structure_id' => $request->integer('structure_id') ?: null,
        ]);

        return response()->json([
            'data' => $this->detail($rapportPersonnalise, $request),
            'apercu' => $apercu,
            'exercice' => $exercice->only(['id', 'annee', 'libelle']),
        ]);
    }

    public function store(RapportPersonnaliseRequest $request): JsonResponse
    {
        abort_unless($request->user()?->can('reporting.create'), 403);
        $resultat = $this->idempotence->executer($request, 'reporting.modele.store', function () use ($request) {
            $modele = RapportPersonnalise::create([
                ...$request->validated(),
                'code' => $this->code(),
                'cree_par' => $request->user()->id,
                'partage' => $request->boolean('partage'),
                'actif' => $request->boolean('actif', true),
            ]);

            return [
                'status' => 201,
                'body' => [
                    'message' => 'Modèle de rapport enregistré.',
                    'code' => 'cree',
                    'modele' => $this->detail($modele->fresh()->load('auteur'), $request),
                ],
            ];
        });

        return response()->json($resultat['body'], $resultat['status']);
    }

    public function update(RapportPersonnaliseRequest $request, RapportPersonnalise $rapportPersonnalise): JsonResponse
    {
        abort_unless($request->user()?->can('reporting.create'), 403);
        abort_unless($this->peutModifier($request, $rapportPersonnalise), 403);
        $rapportPersonnalise->update([
            ...$request->validated(),
            'partage' => $request->boolean('partage'),
            'actif' => $request->boolean('actif', true),
        ]);

        return response()->json([
            'message' => 'Modèle mis à jour.',
            'code' => 'mis_a_jour',
            'modele' => $this->detail($rapportPersonnalise->fresh()->load('auteur'), $request),
        ]);
    }

    public function destroy(Request $request, RapportPersonnalise $rapportPersonnalise): JsonResponse
    {
        abort_unless($request->user()?->can('reporting.create'), 403);
        abort_unless($this->peutModifier($request, $rapportPersonnalise), 403);
        $rapportPersonnalise->delete();

        return response()->json(['message' => 'Modèle supprimé.', 'code' => 'supprime']);
    }

    public function generer(Request $request, RapportPersonnalise $rapportPersonnalise): Response
    {
        $this->authorize('generate', Rapport::class);
        $this->authorizeModele($request, $rapportPersonnalise);
        $format = $request->string('format', 'pdf')->toString();
        $exercice = $this->exercice($request);
        $resultat = $this->renderer->generer('personnalise', $exercice, $format, [
            'personnalise_id' => $rapportPersonnalise->id,
            'exercice_annee' => $exercice->annee,
            'structure_id' => $request->integer('structure_id') ?: null,
        ], $request->user());

        return response($resultat['contenu'], 200, [
            'Content-Type' => $resultat['mime'],
            'Content-Disposition' => 'attachment; filename="'.$resultat['filename'].'"',
        ]);
    }

    /** @return array<string, mixed> */
    private function resume(RapportPersonnalise $m, Request $request): array
    {
        $sources = RapportPersonnaliseService::SOURCES;

        return [
            'id' => $m->id,
            'code' => $m->code,
            'titre' => $m->titre,
            'source' => $m->source,
            'source_libelle' => $sources[$m->source]['label'] ?? $m->source,
            'partage' => (bool) $m->partage,
            'auteur' => $m->auteur?->name,
            'actions' => [
                'modifier' => $this->peutModifier($request, $m),
                'generer' => (bool) $request->user()?->can('reporting.generate'),
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function detail(RapportPersonnalise $m, Request $request): array
    {
        return array_merge($this->resume($m, $request), [
            'description' => $m->description,
            'colonnes' => $m->colonnes ?? [],
            'actif' => (bool) $m->actif,
            'cree_par' => $m->cree_par,
        ]);
    }

    /** @return array<string, string> */
    private function sourcesLibelles(): array
    {
        return collect(RapportPersonnaliseService::SOURCES)->mapWithKeys(fn ($def, $cle) => [$cle => $def['label']])->all();
    }

    private function code(): string
    {
        $seq = RapportPersonnalise::withTrashed()->count() + 1;

        return sprintf('RPT-PERS-%03d', $seq);
    }

    private function exercice(Request $request): Exercice
    {
        return $request->filled('exercice')
            ? Exercice::findOrFail($request->integer('exercice'))
            : (Exercice::firstWhere('statut', 'ouvert') ?? Exercice::latest('annee')->firstOrFail());
    }

    private function authorizeModele(Request $request, RapportPersonnalise $modele): void
    {
        $this->authorize('viewAny', Rapport::class);
        abort_unless(
            $modele->actif && ($modele->partage || $modele->cree_par === $request->user()->id),
            403
        );
    }

    private function peutModifier(Request $request, RapportPersonnalise $modele): bool
    {
        return $modele->cree_par === $request->user()->id || (bool) $request->user()?->can('reporting.admin');
    }
}
