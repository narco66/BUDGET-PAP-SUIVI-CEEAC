<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\AppelFondsRequest;
use App\Http\Resources\AppelFondsResource;
use App\Models\AppelFonds;
use App\Models\EtatMembre;
use App\Models\Relance;
use App\Models\SourceFinancement;
use App\Services\Api\IdempotenceActionService;
use App\Services\Recettes\AppelFondsService;
use App\Services\Recettes\RelanceService;
use App\Support\Montant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use RuntimeException;

class AppelFondsApiController extends Controller
{
    public function __construct(
        private readonly AppelFondsService $service,
        private readonly RelanceService $relances,
        private readonly IdempotenceActionService $idempotence,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', AppelFonds::class);
        $exerciceId = $request->integer('exercice') ?: null;

        $items = AppelFonds::with(['exercice', 'etatMembre', 'sourceFinancement'])
            ->when($exerciceId, fn ($q) => $q->where('exercice_id', $exerciceId))
            ->when($request->filled('statut'), fn ($q) => $q->where('statut', $request->statut))
            ->when($request->filled('q'), function ($q) use ($request) {
                $terme = '%'.$request->string('q').'%';
                $q->where(function ($inner) use ($terme) {
                    $inner->where('reference', 'like', $terme)->orWhere('objet', 'like', $terme);
                });
            })
            ->orderByDesc('date_emission')
            ->orderByDesc('id')
            ->paginate(min(100, max(1, (int) $request->integer('per_page', 20))));

        $parEtat = AppelFonds::query()
            ->selectRaw('etat_membre_id, SUM(montant_appele) appele, SUM(montant_regle) regle')
            ->when($exerciceId, fn ($q) => $q->where('exercice_id', $exerciceId))
            ->whereNotNull('etat_membre_id')
            ->where('statut', '!=', 'annule')
            ->groupBy('etat_membre_id')
            ->with('etatMembre')
            ->get()
            ->map(function ($ligne) {
                $appele = (float) $ligne->appele;
                $regle = (float) $ligne->regle;

                return [
                    'etat' => $ligne->etatMembre?->pays,
                    'montant_appele' => Montant::api($appele),
                    'montant_regle' => Montant::api($regle),
                    'solde' => Montant::api(max($appele - $regle, 0)),
                    'taux_paiement' => Montant::api($appele > 0 ? $regle / $appele * 100 : 0, 1),
                ];
            });

        return AppelFondsResource::collection($items)->additional(['par_etat' => $parEtat]);
    }

    public function options(): JsonResponse
    {
        $this->authorize('create', AppelFonds::class);

        return response()->json([
            'etats' => EtatMembre::query()->where('actif', true)->orderBy('pays')->get(['id', 'code', 'pays']),
            'sources' => SourceFinancement::query()->where('actif', true)->orderBy('libelle')->get(['id', 'code', 'libelle']),
            'canaux_relance' => Relance::CANAUX,
        ]);
    }

    public function store(AppelFondsRequest $request): JsonResponse
    {
        $this->authorize('create', AppelFonds::class);

        try {
            $appel = $this->service->emettre($request->validated(), $request->user());
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage(), 'code' => 'transition_invalide'], 422);
        }

        return response()->json([
            'message' => 'Appel de fonds émis.',
            'code' => 'emis',
            'appel' => (new AppelFondsResource($this->recharger($appel)))->resolve($request),
        ], 201);
    }

    public function show(Request $request, AppelFonds $appel): AppelFondsResource
    {
        $this->authorize('view', $appel);
        $appel->load(['exercice', 'etatMembre', 'sourceFinancement', 'emisPar', 'relances', 'plansApurement']);

        return (new AppelFondsResource($appel))->additional([
            'canaux_relance' => Relance::CANAUX,
        ]);
    }

    public function recouvrer(Request $request, AppelFonds $appel): JsonResponse
    {
        $this->authorize('recouvrer', $appel);
        $data = $request->validate([
            'montant' => ['required', 'numeric', 'min:0.01'],
            'observations' => ['nullable', 'string', 'max:2000'],
        ]);

        $resultat = $this->idempotence->executer($request, 'appel.recouvrer.'.$appel->id, function () use ($request, $appel, $data) {
            $this->assertVersion($request, $appel);
            try {
                $this->service->enregistrerRecouvrement($appel->fresh(), (float) $data['montant'], $data['observations'] ?? null);
            } catch (RuntimeException $e) {
                return ['status' => 422, 'body' => ['message' => $e->getMessage(), 'code' => 'transition_invalide']];
            }

            return [
                'status' => 200,
                'body' => [
                    'message' => 'Recouvrement enregistré.',
                    'code' => 'recouvre',
                    'appel' => (new AppelFondsResource($this->recharger($appel)))->resolve($request),
                ],
            ];
        });

        return response()->json($resultat['body'], $resultat['status']);
    }

    public function relancer(Request $request, AppelFonds $appel): JsonResponse
    {
        $this->authorize('recouvrer', $appel);
        $data = $request->validate([
            'canal' => ['required', 'in:'.implode(',', array_keys(Relance::CANAUX))],
            'destinataire' => ['nullable', 'string', 'max:255'],
            'objet' => ['nullable', 'string', 'max:255'],
            'observations' => ['nullable', 'string', 'max:2000'],
        ]);

        $resultat = $this->idempotence->executer($request, 'appel.relancer.'.$appel->id, function () use ($request, $appel, $data) {
            $this->assertVersion($request, $appel);
            try {
                $this->relances->relancer($appel->fresh(), $data, $request->user());
            } catch (RuntimeException $e) {
                return ['status' => 422, 'body' => ['message' => $e->getMessage(), 'code' => 'transition_invalide']];
            }

            return [
                'status' => 200,
                'body' => [
                    'message' => 'Relance enregistrée.',
                    'code' => 'relance',
                    'appel' => (new AppelFondsResource($this->recharger($appel)))->resolve($request),
                ],
            ];
        });

        return response()->json($resultat['body'], $resultat['status']);
    }

    public function annuler(Request $request, AppelFonds $appel): JsonResponse
    {
        $this->authorize('annuler', $appel);

        $resultat = $this->idempotence->executer($request, 'appel.annuler.'.$appel->id, function () use ($request, $appel) {
            $this->assertVersion($request, $appel);
            try {
                $this->service->annuler($appel->fresh());
            } catch (RuntimeException $e) {
                return ['status' => 422, 'body' => ['message' => $e->getMessage(), 'code' => 'transition_invalide']];
            }

            return [
                'status' => 200,
                'body' => [
                    'message' => 'Appel de fonds annulé.',
                    'code' => 'annule',
                    'appel' => (new AppelFondsResource($this->recharger($appel)))->resolve($request),
                ],
            ];
        });

        return response()->json($resultat['body'], $resultat['status']);
    }

    private function recharger(AppelFonds $appel): AppelFonds
    {
        return $appel->fresh()->load(['exercice', 'etatMembre', 'sourceFinancement', 'relances', 'plansApurement']);
    }

    private function assertVersion(Request $request, AppelFonds $appel): void
    {
        $attendu = $request->header('If-Match');
        if ($attendu === null || $attendu === '') {
            return;
        }

        if ($attendu !== $appel->updated_at?->toIso8601String()) {
            abort(response()->json([
                'message' => 'L’appel de fonds a été modifié. Rechargez avant d’agir.',
                'code' => 'conflict',
            ], 409));
        }
    }
}
