<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\CampagneSuiviResource;
use App\Models\Activite;
use App\Models\CampagneSuivi;
use App\Models\Collecte;
use App\Models\Pap;
use App\Services\Api\IdempotenceActionService;
use App\Services\Suivi\SuiviEvaluationService;
use App\Services\Suivi\SuiviPhysiqueService;
use App\Support\Montant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CampagneSuiviApiController extends Controller
{
    public function __construct(private readonly IdempotenceActionService $idempotence) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', CampagneSuivi::class);

        $items = CampagneSuivi::with('exercice')->withCount('collectes')
            ->when($request->filled('exercice'), fn ($q) => $q->where('exercice_id', $request->integer('exercice')))
            ->when($request->filled('statut'), fn ($q) => $q->where('statut', $request->statut))
            ->latest('id')
            ->paginate(min(100, max(1, (int) $request->integer('per_page', 20))));

        return CampagneSuiviResource::collection($items)->additional([
            'peut_creer' => $request->user()?->can('create', CampagneSuivi::class),
        ]);
    }

    public function show(Request $request, CampagneSuivi $campagne): JsonResponse
    {
        $this->authorize('view', $campagne);
        $campagne->load('exercice');
        $campagne->loadCount('collectes');
        $campagne->consolidation_api = $this->consolidation($campagne->id);

        $activites = Activite::query()
            ->whereHas('sousProduit.produit.axe.pilier', fn ($q) => $q->where('exercice_id', $campagne->exercice_id))
            ->with(['structure', 'collectes' => fn ($q) => $q->where('campagne_id', $campagne->id)])
            ->orderBy('code')
            ->paginate(min(100, max(1, (int) $request->integer('per_page', 25))));

        return response()->json([
            'data' => (new CampagneSuiviResource($campagne))->resolve($request),
            'activites' => [
                'data' => $activites->getCollection()->map(function (Activite $a) {
                    $col = $a->collectes->first();

                    return [
                        'id' => $a->id,
                        'code' => $a->code,
                        'intitule' => $a->intitule,
                        'montant_total' => Montant::api($a->montant_total),
                        'structure' => $a->structure?->only(['id', 'sigle', 'libelle']),
                        'collecte' => $col ? [
                            'id' => $col->id,
                            'statut' => $col->statut,
                            'taux_physique' => Montant::api($col->taux_physique),
                            'taux_financier' => Montant::api($col->taux_financier),
                            'montant_realise' => Montant::api($col->montant_realise),
                            'ecart' => Montant::api($col->ecart),
                        ] : null,
                    ];
                })->values(),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', CampagneSuivi::class);
        $resultat = $this->idempotence->executer($request, 'campagne.store', function () use ($request) {
            $data = $request->validate([
                'exercice_id' => ['required', 'exists:exercices,id'],
                'libelle' => ['required', 'string', 'max:255'],
                'type' => ['required', Rule::in(['mensuel', 'trimestriel', 'semestriel', 'annuel', 'pluriannuel'])],
                'periode' => ['required', 'string', 'max:20', Rule::unique('campagnes_suivi')->where('exercice_id', $request->exercice_id)],
                'date_debut' => ['nullable', 'date'],
                'date_fin' => ['nullable', 'date'],
            ]);
            $campagne = CampagneSuivi::create($data);

            return [
                'status' => 201,
                'body' => [
                    'message' => 'Campagne créée.',
                    'code' => 'cree',
                    'campagne' => (new CampagneSuiviResource($campagne->fresh()->load('exercice')))->resolve($request),
                ],
            ];
        });

        return response()->json($resultat['body'], $resultat['status']);
    }

    public function cloturer(Request $request, CampagneSuivi $campagne, SuiviPhysiqueService $suiviPhysique, SuiviEvaluationService $evaluation): JsonResponse
    {
        abort_unless($request->user()?->can('suivi.valider'), 403);
        abort_unless($campagne->estOuverte(), 422, 'Campagne déjà clôturée.');

        $resultat = $suiviPhysique->cloturerCampagne($campagne);
        $evaluation->genererEvaluation($campagne->exercice_id, $campagne->id, $request->user()->id);

        return response()->json([
            'message' => "Campagne clôturée — {$resultat['produits_consolides']} produit(s), {$resultat['sous_produits_consolides']} sous-produit(s), {$resultat['alertes_creees']} alerte(s).",
            'code' => 'cloturee',
            'campagne' => (new CampagneSuiviResource($campagne->fresh()->load('exercice')))->resolve($request),
        ]);
    }

    public function destroy(Request $request, CampagneSuivi $campagne): JsonResponse
    {
        $this->authorize('delete', $campagne);
        $campagne->delete();

        return response()->json(['message' => 'Campagne supprimée.', 'code' => 'supprime']);
    }

    public function enregistrerCollecte(Request $request, CampagneSuivi $campagne, Activite $activite, SuiviPhysiqueService $suiviPhysique): JsonResponse
    {
        abort_unless($campagne->estOuverte(), 422, 'Campagne clôturée.');
        abort_unless(
            Activite::whereKey($activite->id)
                ->whereHas('sousProduit.produit.axe.pilier', fn ($q) => $q->where('exercice_id', $campagne->exercice_id))
                ->exists(),
            422,
            'Activité hors exercice de la campagne.'
        );

        $pap = Pap::where('exercice_id', $campagne->exercice_id)->first();
        if ($pap && $pap->statut !== 'publie') {
            abort(422, 'Le PAP de cet exercice doit être publié avant la saisie de suivi.');
        }

        $collecte = Collecte::firstOrNew(['campagne_id' => $campagne->id, 'activite_id' => $activite->id]);
        if ($collecte->exists) {
            $this->authorize('update', $collecte);
        } else {
            $this->authorize('create', Collecte::class);
        }

        $data = $request->validate([
            'taux_physique' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'quantite_prevue' => ['nullable', 'numeric', 'min:0'],
            'quantite_realisee' => ['nullable', 'numeric', 'min:0'],
            'unite' => ['nullable', 'string', 'max:50'],
            'date_realisation' => ['nullable', 'date'],
            'montant_realise' => ['required', 'numeric', 'min:0'],
            'cause_ecart' => ['nullable', 'string', 'max:2000'],
            'commentaire' => ['nullable', 'string', 'max:1000'],
        ]);

        if (empty($data['taux_physique']) && empty($data['quantite_prevue'])) {
            $data['taux_physique'] = null;
        }

        $collecte->fill($data);
        $collecte->setRelation('activite', $activite);
        $suiviPhysique->enrichirDepuisTaches($collecte);

        if ((float) ($collecte->taux_physique ?? 0) <= 0 && empty($collecte->quantite_prevue)) {
            throw ValidationException::withMessages([
                'taux_physique' => 'Indiquez un taux physique ou des quantités prévue/réalisée.',
            ]);
        }
        $collecte->recalculer();
        $collecte->statut = 'saisie';
        $collecte->saisi_par ??= $request->user()->id;
        $collecte->save();

        return response()->json([
            'message' => "Collecte enregistrée pour l'activité {$activite->code}.",
            'code' => 'saisie',
            'collecte' => [
                'id' => $collecte->id,
                'statut' => $collecte->statut,
                'taux_physique' => Montant::api($collecte->taux_physique),
                'taux_financier' => Montant::api($collecte->taux_financier),
                'montant_realise' => Montant::api($collecte->montant_realise),
                'ecart' => Montant::api($collecte->ecart),
            ],
        ]);
    }

    private function consolidation(int $campagneId)
    {
        return DB::table('collectes as c')
            ->join('activites as a', 'c.activite_id', '=', 'a.id')
            ->leftJoin('structures as s', 'a.structure_id', '=', 's.id')
            ->leftJoin('lignes_budgetaires as lb', 'a.ligne_budgetaire_id', '=', 'lb.id')
            ->where('c.campagne_id', $campagneId)->where('c.statut', 'validee')
            ->groupBy('s.id', 's.libelle')
            ->selectRaw('COALESCE(s.libelle, "(non rattachée)") libelle, COUNT(*) n,
                         ROUND(AVG(c.taux_physique),1) phys,
                         ROUND(AVG(CASE
                            WHEN a.ligne_budgetaire_id IS NOT NULL AND a.montant_total > 0
                            THEN (lb.montant_paye / a.montant_total * 100)
                            ELSE c.taux_financier
                         END),1) fin')
            ->orderByDesc('n')->get();
    }
}
