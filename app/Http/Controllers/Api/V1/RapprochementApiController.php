<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\RapprochementBancaireResource;
use App\Models\Exercice;
use App\Models\Paiement;
use App\Models\RapprochementBancaire;
use App\Models\RapprochementLigne;
use App\Services\Api\IdempotenceActionService;
use App\Services\Depense\RapprochementBancaireService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use RuntimeException;

class RapprochementApiController extends Controller
{
    public function __construct(
        private readonly RapprochementBancaireService $service,
        private readonly IdempotenceActionService $idempotence,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->autoriser($request);

        $query = RapprochementBancaire::with('exercice', 'createur')
            ->when($request->filled('exercice'), fn ($q) => $q->where('exercice_id', $request->integer('exercice')))
            ->when($request->filled('statut'), fn ($q) => $q->where('statut', $request->statut))
            ->latest();

        return RapprochementBancaireResource::collection(
            $query->paginate(min(100, max(1, (int) $request->integer('per_page', 20))))
        );
    }

    public function store(Request $request): JsonResponse
    {
        $this->autoriser($request, ecrire: true);
        $data = $request->validate([
            'exercice_id' => ['required', 'exists:exercices,id'],
            'intitule' => ['required', 'string', 'max:255'],
            'compte_bancaire' => ['nullable', 'string', 'max:50'],
            'date_debut' => ['nullable', 'date'],
            'date_fin' => ['nullable', 'date', 'after_or_equal:date_debut'],
        ]);

        $session = $this->service->creer(Exercice::findOrFail($data['exercice_id']), $data, $request->user());

        return response()->json([
            'message' => 'Session de rapprochement créée.',
            'code' => 'cree',
            'session' => $this->payloadSession($request, $session),
        ], 201);
    }

    public function show(Request $request, RapprochementBancaire $rapprochement): RapprochementBancaireResource
    {
        $this->autoriser($request);
        $rapprochement->load(['lignes.paiement', 'exercice', 'createur']);

        return (new RapprochementBancaireResource($rapprochement))
            ->additional(['paiements_eligibles' => $this->eligibles($rapprochement)]);
    }

    public function importer(Request $request, RapprochementBancaire $rapprochement): JsonResponse
    {
        $this->autoriser($request, ecrire: true);
        $this->assertOuvert($rapprochement);

        $data = $request->validate([
            'lignes' => ['required'],
        ]);

        $lignes = is_string($data['lignes'])
            ? $this->service->parserReleve($data['lignes'])
            : $this->normaliserLignes(is_array($data['lignes']) ? $data['lignes'] : []);

        if ($lignes === []) {
            return response()->json(['message' => 'Aucune ligne valide à importer.', 'code' => 'vide'], 422);
        }

        $count = $this->service->importerLignes($rapprochement, $lignes);

        return response()->json([
            'message' => "{$count} ligne(s) importée(s).",
            'code' => 'importe',
            'importees' => $count,
            'session' => $this->payloadSession($request, $rapprochement, true),
        ]);
    }

    public function auto(Request $request, RapprochementBancaire $rapprochement): JsonResponse
    {
        $this->autoriser($request, ecrire: true);
        $this->assertOuvert($rapprochement);

        $resultat = $this->idempotence->executer($request, 'rapprochement.auto.'.$rapprochement->id, function () use ($request, $rapprochement) {
            $matches = $this->service->rapprocherAuto($rapprochement->fresh());

            return [
                'status' => 200,
                'body' => [
                    'message' => "{$matches} rapprochement(s) automatique(s).",
                    'code' => 'auto',
                    'matches' => $matches,
                    'session' => $this->payloadSession($request, $rapprochement, true),
                ],
            ];
        });

        return response()->json($resultat['body'], $resultat['status']);
    }

    public function lier(Request $request, RapprochementBancaire $rapprochement): JsonResponse
    {
        $this->autoriser($request, ecrire: true);
        $this->assertOuvert($rapprochement);
        $data = $request->validate([
            'ligne_id' => ['required', 'exists:rapprochement_lignes,id'],
            'paiement_id' => ['required', 'exists:paiements,id'],
        ]);

        try {
            $this->service->rapprocherManuel(
                $rapprochement,
                RapprochementLigne::findOrFail($data['ligne_id']),
                Paiement::findOrFail($data['paiement_id']),
            );
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage(), 'code' => 'credits_ou_transition'], 422);
        }

        return response()->json([
            'message' => 'Ligne rapprochée.',
            'code' => 'lie',
            'session' => $this->payloadSession($request, $rapprochement, true),
        ]);
    }

    public function cloturer(Request $request, RapprochementBancaire $rapprochement): JsonResponse
    {
        $this->autoriser($request, ecrire: true);

        $resultat = $this->idempotence->executer($request, 'rapprochement.cloturer.'.$rapprochement->id, function () use ($request, $rapprochement) {
            try {
                $this->service->cloturer($rapprochement->fresh());
            } catch (RuntimeException $e) {
                return ['status' => 422, 'body' => ['message' => $e->getMessage(), 'code' => 'transition_invalide']];
            }

            return [
                'status' => 200,
                'body' => [
                    'message' => 'Session clôturée.',
                    'code' => 'clos',
                    'session' => $this->payloadSession($request, $rapprochement),
                ],
            ];
        });

        return response()->json($resultat['body'], $resultat['status']);
    }

    private function autoriser(Request $request, bool $ecrire = false): void
    {
        $user = $request->user();
        $lecture = $user?->can('depenses.admin')
            || $user?->can('paiement.valider')
            || $user?->can('depenses.view');
        $ecriture = $user?->can('depenses.admin') || $user?->can('paiement.valider');

        abort_unless($ecrire ? $ecriture : $lecture, 403);
    }

    private function assertOuvert(RapprochementBancaire $session): void
    {
        if (! in_array($session->statut, ['brouillon', 'en_cours'], true)) {
            abort(response()->json([
                'message' => 'Cette session n’est plus modifiable.',
                'code' => 'transition_invalide',
            ], 422));
        }
    }

    /** @return array<string, mixed> */
    private function payloadSession(Request $request, RapprochementBancaire $session, bool $avecEligibles = false): array
    {
        $fresh = $session->fresh()->load(['lignes.paiement', 'exercice', 'createur']);
        $data = (new RapprochementBancaireResource($fresh))->resolve($request);
        if ($avecEligibles) {
            $data['paiements_eligibles'] = $this->eligibles($fresh);
        }

        return $data;
    }

    /** @return list<array<string, mixed>> */
    private function eligibles(RapprochementBancaire $session): array
    {
        return $this->service->paiementsEligibles($session)->map(fn ($p) => [
            'id' => $p->id,
            'numero' => $p->numero,
            'montant' => \App\Support\Montant::api($p->montant),
            'reference_bancaire' => $p->reference_bancaire,
        ])->values()->all();
    }

    /**
     * @param  list<mixed>  $lignes
     * @return list<array{date_operation:string,libelle:string,reference_bancaire?:string|null,montant:float}>
     */
    private function normaliserLignes(array $lignes): array
    {
        $sortie = [];
        foreach ($lignes as $ligne) {
            if (! is_array($ligne) || empty($ligne['montant']) || empty($ligne['date_operation']) || empty($ligne['libelle'])) {
                continue;
            }
            $sortie[] = [
                'date_operation' => (string) $ligne['date_operation'],
                'libelle' => (string) $ligne['libelle'],
                'reference_bancaire' => $ligne['reference_bancaire'] ?? null,
                'montant' => (float) $ligne['montant'],
            ];
        }

        return $sortie;
    }
}
