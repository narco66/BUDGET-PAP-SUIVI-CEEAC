<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Api\IdempotenceActionService;
use App\Services\Rbm\RbmNoeudService;
use App\Support\RbmAuthorization;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class RbmNoeudApiController extends Controller
{
    public function __construct(
        private readonly RbmNoeudService $noeuds,
        private readonly IdempotenceActionService $idempotence,
    ) {}

    public function options(Request $request, string $niveau): JsonResponse
    {
        abort_unless($request->user()?->can('rbm.voir'), 403);
        $this->noeuds->config($niveau);

        return response()->json($this->noeuds->options(
            $niveau,
            $request->user(),
            $request->integer('exercice') ?: null,
        ));
    }

    public function show(Request $request, string $niveau, int $id): JsonResponse
    {
        abort_unless($request->user()?->can('rbm.voir'), 403);
        $noeud = $this->noeuds->trouver($niveau, $id);
        $this->noeuds->assertNoeudDansPerimetre($request->user(), $niveau, $noeud);

        return response()->json([
            'data' => $this->noeuds->serialiser($niveau, $noeud),
            'peut_modifier' => RbmAuthorization::peut($request->user(), $niveau, 'modifier'),
        ]);
    }

    public function store(Request $request, string $niveau): JsonResponse
    {
        abort_unless(RbmAuthorization::peut($request->user(), $niveau, 'creer'), 403);

        $resultat = $this->idempotence->executer($request, 'rbm.creer.'.$niveau, function () use ($request, $niveau) {
            try {
                $noeud = $this->noeuds->creer($niveau, $request);
            } catch (RuntimeException $e) {
                return ['status' => 422, 'body' => ['message' => $e->getMessage(), 'code' => 'transition_invalide']];
            }

            return [
                'status' => 201,
                'body' => [
                    'message' => 'Élément créé.',
                    'code' => 'cree',
                    'data' => $this->noeuds->serialiser($niveau, $noeud),
                ],
            ];
        });

        return response()->json($resultat['body'], $resultat['status']);
    }

    public function update(Request $request, string $niveau, int $id): JsonResponse
    {
        abort_unless(RbmAuthorization::peut($request->user(), $niveau, 'modifier'), 403);

        $resultat = $this->idempotence->executer($request, 'rbm.modifier.'.$niveau.'.'.$id, function () use ($request, $niveau, $id) {
            try {
                $noeud = $this->noeuds->modifier($niveau, $id, $request);
            } catch (RuntimeException $e) {
                return ['status' => 422, 'body' => ['message' => $e->getMessage(), 'code' => 'transition_invalide']];
            }

            return [
                'status' => 200,
                'body' => [
                    'message' => 'Élément mis à jour.',
                    'code' => 'modifie',
                    'data' => $this->noeuds->serialiser($niveau, $noeud),
                ],
            ];
        });

        return response()->json($resultat['body'], $resultat['status']);
    }
}
