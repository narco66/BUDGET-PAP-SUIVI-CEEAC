<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Exercice;
use App\Models\Rapport;
use App\Models\RapportCategorie;
use App\Models\RapportModele;
use App\Services\Reporting\RapportAuditService;
use App\Services\Reporting\RapportComposer;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** Endpoints REST catalogue et historique Reporting (P3). */
class ReportingApiController extends Controller
{
    public function __construct(
        private readonly RapportAuditService $audit,
    ) {
    }

    public function catalogue(Request $request): JsonResource
    {
        $this->assertReporting($request);

        $categories = RapportCategorie::with(['modeles' => fn ($q) => $q->where('actif', true)->orderBy('titre')])
            ->where('actif', true)
            ->orderBy('ordre')
            ->get()
            ->map(fn (RapportCategorie $c) => [
                'code' => $c->code,
                'libelle' => $c->libelle,
                'modeles' => $c->modeles->map(fn (RapportModele $m) => [
                    'type' => $m->type,
                    'titre' => $m->titre,
                    'description' => $m->description,
                    'parametres_schema' => $m->parametres_schema,
                ])->values(),
            ]);

        $this->audit->journaliser('api_catalogue', null, $request->user(), $request);

        return new JsonResource(['categories' => $categories]);
    }

    public function rapports(Request $request): JsonResource
    {
        $this->assertReporting($request);

        $query = Rapport::with(['exercice', 'generePar'])
            ->when($request->filled('type'), fn ($q) => $q->where('type', $request->type))
            ->when($request->filled('exercice'), function ($q) use ($request) {
                $ex = Exercice::find($request->integer('exercice'))
                    ?? Exercice::firstWhere('annee', $request->integer('exercice'));
                if ($ex) {
                    $q->where('exercice_id', $ex->id);
                }
            })
            ->latest();

        $paginated = $query->paginate(min(50, max(1, (int) $request->integer('limit', 20))));

        $this->audit->journaliser('api_liste', null, $request->user(), $request, [
            'total' => $paginated->total(),
        ]);

        return JsonResource::collection($paginated->through(fn (Rapport $r) => $this->formatRapport($r)));
    }

    public function show(Request $request, Rapport $rapport): JsonResource
    {
        $this->assertReporting($request);

        $rapport->load(['exercice', 'generePar', 'validePar', 'publiePar', 'modele.categorie', 'ipsas']);

        $this->audit->journaliser('api_consult', $rapport, $request->user(), $request);

        return new JsonResource(array_merge($this->formatRapport($rapport), [
            'statut_workflow' => $rapport->statut_workflow,
            'verrouille' => $rapport->verrouille,
            'ged_indexe' => $rapport->ged_indexe,
            'checksum_sources' => $rapport->checksum_sources,
            'hash' => $rapport->hash,
            'verification_url' => $rapport->urlVerification(),
            'categorie' => $rapport->modele?->categorie?->code,
            'ipsas' => $rapport->ipsas ? [
                'reference_note' => $rapport->ipsas->reference_note,
                'donnees_preparatoires' => $rapport->ipsas->donnees_preparatoires,
            ] : null,
        ]));
    }

    public function types(Request $request): JsonResource
    {
        $this->assertReporting($request);

        return new JsonResource([
            'types' => collect(RapportComposer::TYPES)->map(fn ($titre, $type) => [
                'type' => $type,
                'titre' => $titre,
            ])->values(),
        ]);
    }

    private function assertReporting(Request $request): void
    {
        abort_unless($request->user()?->can('reporting.view'), 403);
    }

    /** @return array<string, mixed> */
    private function formatRapport(Rapport $r): array
    {
        return [
            'id' => $r->id,
            'reference' => $r->reference,
            'type' => $r->type,
            'titre' => $r->titre,
            'titre_catalogue' => RapportComposer::TYPES[$r->type] ?? $r->type,
            'exercice' => $r->exercice?->annee,
            'periode' => $r->periode,
            'format' => $r->format,
            'version' => $r->version,
            'statut' => $r->statut,
            'statut_workflow' => $r->statut_workflow,
            'genere_par' => $r->generePar?->name,
            'genere_le' => $r->created_at?->toIso8601String(),
        ];
    }
}
