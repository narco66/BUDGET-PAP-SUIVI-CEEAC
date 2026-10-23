<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\EngagementResource;
use App\Http\Resources\GouvernanceResource;
use App\Http\Resources\LigneBudgetaireResource;
use App\Http\Resources\StructureResource;
use App\Models\Engagement;
use App\Models\Exercice;
use App\Models\LigneBudgetaire;
use App\Models\Structure;
use App\Services\AccessScopeService;
use App\Services\Institution\InstitutionGouvernanceService;
use App\Services\Institution\InstitutionMetricsService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Resources\Json\JsonResource;

/** Endpoints de lecture des principales entités (D12 — EF-D12-01). */
class DonneesApiController extends Controller
{
    public function __construct(
        private readonly AccessScopeService $scopes,
        private readonly InstitutionGouvernanceService $gouvernance,
    ) {}
    public function exercices(): AnonymousResourceCollection
    {
        return JsonResource::collection(
            Exercice::orderByDesc('annee')->get(['id', 'code', 'annee', 'libelle', 'statut', 'est_actif', 'date_debut', 'date_fin', 'date_cloture', 'devise'])
        );
    }

    public function lignesBudgetaires(Request $request): AnonymousResourceCollection
    {
        return LigneBudgetaireResource::collection(
            LigneBudgetaire::with('rubrique', 'structure')
                ->when($request->filled('exercice'), fn ($q) => $q->where('exercice_id', $request->exercice))
                ->orderBy('code')->paginate(50)
        );
    }

    public function engagements(Request $request): AnonymousResourceCollection
    {
        return EngagementResource::collection(
            Engagement::with('imputations.ligneBudgetaire')
                ->when($request->filled('exercice'), fn ($q) => $q->where('exercice_id', $request->exercice))
                ->when($request->filled('statut'), fn ($q) => $q->where('statut', $request->statut))
                ->latest()->paginate(50)
        );
    }

    public function structures(Request $request): AnonymousResourceCollection
    {
        $query = Structure::query()
            ->with(['parent', 'responsable', 'structureOfficielle', 'parent.parent.parent.parent'])
            ->whereNull('deleted_at')
            ->orderBy('niveau')
            ->orderBy('ordre');

        $this->scopes->filtrerReferentielStructures($query, $request->user());

        if ($request->filled('type')) {
            $query->where('type', $request->string('type'));
        }

        if ($request->filled('code')) {
            $query->where('code', $request->string('code'));
        }

        $perPage = min(100, max(1, (int) $request->integer('per_page', 50)));

        return StructureResource::collection($query->paginate($perPage));
    }

    public function institution(Request $request, InstitutionMetricsService $metrics): JsonResource
    {
        $indicateurs = $metrics->indicateurs($request->integer('exercice') ?: null);

        if (! $this->scopes->estGlobal($request->user())) {
            $ids = $this->scopes->structuresAutorisees($request->user());
            $codesAutorises = Structure::whereIn('id', $ids)->pluck('code');

            foreach (['effectifs_departements', 'effectifs_directions', 'effectifs_services'] as $cle) {
                $indicateurs[$cle] = collect($indicateurs[$cle])
                    ->filter(fn ($row) => $codesAutorises->contains($row->code))
                    ->values();
            }

            foreach (['budget_departements', 'pap_departements', 'execution_departements', 'projets_departements'] as $cle) {
                $indicateurs[$cle] = collect($indicateurs[$cle])
                    ->filter(fn ($row) => $codesAutorises->contains($row->code))
                    ->values();
            }
        }

        return new JsonResource([
            'exercice_id' => $request->integer('exercice') ?: null,
            'structures_visibles' => $this->scopes->structuresAutorisees($request->user())->count(),
            'indicateurs' => $indicateurs,
        ]);
    }

    public function gouvernance(Request $request): JsonResource
    {
        $user = $request->user();

        $delegations = $this->gouvernance->delegationsActivesPour($user);
        $interims = $this->gouvernance->interimsActifsPour($user);

        return new JsonResource([
            'structure_principale_id' => $user->structure_id,
            'structures_autorisees' => $this->scopes->structuresAutorisees($user)->values(),
            'delegations' => GouvernanceResource::collection($delegations),
            'interims' => GouvernanceResource::collection($interims),
        ]);
    }
}
