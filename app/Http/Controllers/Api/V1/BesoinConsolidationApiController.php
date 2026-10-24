<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\BesoinConsolidation;
use App\Models\Exercice;
use App\Models\Structure;
use App\Policies\ExpressionBesoinPolicy;
use App\Services\Api\IdempotenceActionService;
use App\Services\Depense\BesoinConsolidationService;
use App\Support\Montant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BesoinConsolidationApiController extends Controller
{
    public function __construct(
        private readonly BesoinConsolidationService $consolidation,
        private readonly IdempotenceActionService $idempotence,
    ) {}

    public function show(Request $request): JsonResponse
    {
        abort_unless(app(ExpressionBesoinPolicy::class)->consolider($request->user()), 403);

        $exercice = Exercice::find($request->integer('exercice'))
            ?? Exercice::where('annee', (int) $request->input('annee', date('Y')))->firstOrFail();
        $structureId = $request->filled('structure') ? $request->integer('structure') : null;
        $synthese = $this->consolidation->synthese($exercice->id, $structureId);

        return response()->json([
            'data' => [
                'exercice' => ['id' => $exercice->id, 'annee' => $exercice->annee, 'libelle' => $exercice->libelle],
                'structure_id' => $structureId,
                'structures' => Structure::actif()->orderBy('libelle')->get(['id', 'code', 'libelle', 'sigle']),
                'synthese' => [
                    'total_besoins' => $synthese['total_besoins'],
                    'montant_total' => Montant::api($synthese['montant_total']),
                    'doublons_count' => $synthese['doublons_count'],
                    'par_structure' => collect($synthese['par_structure'])->map(fn ($g) => [
                        'structure_id' => $g['structure_id'] ?? null,
                        'structure' => $g['structure'] ?? null,
                        'count' => $g['count'] ?? 0,
                        'montant' => Montant::api($g['montant'] ?? 0),
                    ])->values(),
                    'doublons' => $synthese['doublons'] ?? [],
                ],
                'historique' => BesoinConsolidation::with('structure', 'auteur')
                    ->where('exercice_id', $exercice->id)
                    ->when($structureId, fn ($q) => $q->where('structure_id', $structureId), fn ($q) => $q->whereNull('structure_id'))
                    ->latest()
                    ->limit(10)
                    ->get()
                    ->map(fn (BesoinConsolidation $c) => [
                        'id' => $c->id,
                        'reference' => $c->reference,
                        'besoins_count' => $c->besoins_count,
                        'doublons_elimines' => $c->doublons_elimines,
                        'montant_total' => Montant::api($c->montant_total),
                        'statut' => $c->statut,
                    ]),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        abort_unless(app(ExpressionBesoinPolicy::class)->consolider($request->user()), 403);
        $data = $request->validate([
            'exercice_id' => ['required', 'exists:exercices,id'],
            'structure_id' => ['nullable', 'exists:structures,id'],
        ]);

        $resultat = $this->idempotence->executer(
            $request,
            'besoins.consolidation.'.($data['exercice_id']).'.'.($data['structure_id'] ?? 'inst'),
            function () use ($request, $data) {
                $c = $this->consolidation->consolider(
                    (int) $data['exercice_id'],
                    $data['structure_id'] ?? null,
                    (int) $request->user()->id,
                );

                return [
                    'status' => 201,
                    'body' => [
                        'message' => "Consolidation enregistrée ({$c->reference}).",
                        'code' => 'consolide',
                        'reference' => $c->reference,
                    ],
                ];
            }
        );

        return response()->json($resultat['body'], $resultat['status']);
    }
}
