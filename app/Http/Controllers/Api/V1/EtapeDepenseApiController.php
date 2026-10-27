<?php

namespace App\Http\Controllers\Api\V1;

use App\Depense\Etapes;
use App\Http\Controllers\Controller;
use App\Http\Requests\WorkflowDecisionSansRenvoiRequest;
use App\Http\Resources\EtapeDepenseResource;
use App\Models\Engagement;
use App\Models\Liquidation;
use App\Models\Ordonnancement;
use App\Models\Paiement;
use App\Services\Api\IdempotenceActionService;
use App\Services\Budget\ExerciceService;
use App\Services\Depense\DepenseQuotaService;
use App\Services\DocumentPdf;
use App\Services\Depense\DevisePaiementService;
use App\Services\Depense\PaiementContrepassationService;
use App\Services\Depense\ServiceFaitService;
use Illuminate\Support\Facades\DB;
use App\Services\WebhookService;
use App\Services\WorkflowService;
use App\Support\Numero;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

class EtapeDepenseApiController extends Controller
{
    public function __construct(
        private readonly WorkflowService $workflow,
        private readonly WebhookService $webhooks,
        private readonly DepenseQuotaService $quotas,
        private readonly IdempotenceActionService $idempotence,
    ) {}

    public function index(Request $request, string $type): AnonymousResourceCollection
    {
        $config = $this->config($type);
        $this->authorize('viewAny', $config['model']);

        $query = $config['model']::with([$config['parent_type'], 'workflowActif', 'createur'])->latest();

        $this->filtrerExercice($query, $type, $request->integer('exercice'));
        app(\App\Services\AccessScopeService::class)->filtrerDossiersDepense($query, $request->user());
        $query->when($request->filled('statut'), fn ($q) => $q->where('statut', $request->statut));
        $query->when($request->filled('q'), function ($q) use ($request) {
            $terme = '%'.$request->string('q').'%';
            $q->where('numero', 'like', $terme);
        });

        return EtapeDepenseResource::collection(
            $query->paginate(min(100, max(1, (int) $request->integer('per_page', 20))))
        );
    }

    public function show(Request $request, string $type, int $id): EtapeDepenseResource
    {
        $item = $this->trouver($type, $id);
        $this->authorize('view', $item);
        $relations = [
            $this->config($type)['parent_type'],
            'workflowActif.etapes.acteur',
            'createur',
        ];
        if ($type === 'ordonnancement') {
            $relations[] = 'liquidation.engagement';
            $relations[] = 'paiements';
        }
        if ($type === 'paiement') {
            $relations[] = 'ordonnancement.paiements';
            $relations[] = 'rejets';
        }
        $item->load($relations);

        return new EtapeDepenseResource($item);
    }

    public function store(Request $request, string $type): JsonResponse
    {
        $config = $this->config($type);
        $this->authorize('create', $config['model']);

        $parent = $config['parent_model']::findOrFail($request->integer('parent'));
        if ($parent->statut !== $config['parent_statut_requis']) {
            return response()->json([
                'message' => "Le {$config['parent_label']} doit être au statut « {$config['parent_statut_requis']} ».",
                'code' => 'transition_invalide',
            ], 422);
        }

        $data = $request->validate($this->regles($config, $type, $request));

        $brouillonLiq = null;
        if ($type === 'liquidation' && $parent instanceof Engagement) {
            $brouillonLiq = Liquidation::query()
                ->where('engagement_id', $parent->id)
                ->where('statut', 'brouillon')
                ->whereDoesntHave('workflowActif')
                ->orderBy('id')
                ->first();
            $autoVierge = $brouillonLiq
                && abs((float) $brouillonLiq->montant - (float) $parent->montant_total) < 0.01
                && blank($brouillonLiq->service_fait)
                && blank($brouillonLiq->numero_facture);
            if (! $autoVierge) {
                $brouillonLiq = null;
            }
        }

        try {
            $montant = $this->montantEtape($type, $parent, $request, $brouillonLiq?->id);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage(), 'code' => 'credits_ou_transition'], 422);
        }

        if ($type === 'liquidation') {
            try {
                $data = array_merge($data, app(\App\Services\Depense\LiquidationMontantsService::class)->depuisRequete($request, $montant)['champs']);
            } catch (RuntimeException $e) {
                return response()->json(['message' => $e->getMessage(), 'code' => 'credits_ou_transition'], 422);
            }
        }

        unset($data['montant']);
        if (array_key_exists('date', $data)) {
            $data[$config['date_cle']] = $data['date'];
            unset($data['date']);
        }

        try {
            $racine = $this->racine($type, $parent);
            app(ExerciceService::class)->assertExerciceOuvert($racine->exercice_id);
            $this->assertLignesActives($racine);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage(), 'code' => 'credits_ou_transition'], 422);
        }
        $annee = $racine->exercice?->annee ?? (int) date('Y');

        if ($brouillonLiq) {
            $brouillonLiq->update([
                ...$this->champsModele($data, $type),
                'montant' => $montant,
            ]);
            $this->completerLiquidation($brouillonLiq->fresh(), $request, $parent, false);

            return response()->json([
                'message' => 'Brouillon de liquidation mis à jour (tranche).',
                'code' => 'mis_a_jour',
                'item' => (new EtapeDepenseResource($this->recharger($type, $brouillonLiq)))->resolve($request),
            ]);
        }

        $creer = function () use ($config, $data, $type, $parent, $montant, $annee, $request) {
            if ($type === 'paiement' && $parent instanceof Ordonnancement) {
                $parent = Ordonnancement::query()->whereKey($parent->id)->lockForUpdate()->firstOrFail();
                $this->quotas->assertPeutPayer($parent, $montant);
            }

            return $config['model']::create([
                ...$this->champsModele($data, $type),
                'numero' => Numero::suivant($config['table'], $config['prefixe'], $annee),
                $config['parent_cle'] => $parent->id,
                'montant' => $montant,
                'statut' => 'brouillon',
                'cree_par' => $request->user()->id,
            ]);
        };

        try {
            $item = $type === 'paiement' ? DB::transaction($creer) : $creer();
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage(), 'code' => 'credits_ou_transition'], 422);
        }

        if ($type === 'liquidation' && $item instanceof Liquidation && $parent instanceof Engagement) {
            $this->completerLiquidation($item, $request, $parent, true);
        }

        if ($type === 'ordonnancement' && $item instanceof Ordonnancement) {
            app(\App\Services\Depense\FractionnementOrdonnancementService::class)->journaliser($item);
        }

        if ($type === 'paiement' && $parent instanceof Ordonnancement) {
            $ordre = $parent->ordresPaiement()->where('statut', 'emis')->first();
            if ($ordre) {
                $item->update(['ordre_paiement_id' => $ordre->id]);
            }
            app(DevisePaiementService::class)->appliquer($item, array_merge($request->all(), ['montant' => $montant]));
            if ($item instanceof Paiement) {
                app(\App\Services\Depense\RibControleService::class)->enregistrerSaisie($item->fresh(), $request->user());
            }
        }

        return response()->json([
            'message' => $config['label'].' créé(e).',
            'code' => 'cree',
            'item' => (new EtapeDepenseResource($this->recharger($type, $item)))->resolve($request),
        ], 201);
    }

    public function soumettre(Request $request, string $type, int $id): JsonResponse
    {
        $config = $this->config($type);
        $item = $this->trouver($type, $id);
        abort_unless($request->user()->can($config['permission'].'.creer'), 403);

        $resultat = $this->idempotence->executer($request, $type.'.soumettre.'.$item->id, function () use ($request, $config, $item, $type) {
            $this->assertVersion($request, $item);
            try {
                $code = $config['workflow'];
                if ($item instanceof Ordonnancement) {
                    $code = app(\App\Services\Depense\SeuilOrdonnancementService::class)->circuit($item);
                }
                $this->workflow->demarrer($item, $code, $request->user());
            } catch (RuntimeException $e) {
                return ['status' => 422, 'body' => ['message' => $e->getMessage(), 'code' => 'transition_invalide']];
            }

            return [
                'status' => 200,
                'body' => [
                    'message' => $config['label'].' soumis(e) à validation.',
                    'code' => 'soumis',
                    'item' => (new EtapeDepenseResource($this->recharger($type, $item)))->resolve($request),
                ],
            ];
        });

        return response()->json($resultat['body'], $resultat['status']);
    }

    public function agir(WorkflowDecisionSansRenvoiRequest $request, string $type, int $id): JsonResponse
    {
        $config = $this->config($type);
        $item = $this->trouver($type, $id);
        $this->authorize('agir', $item);
        $data = $request->validated();
        $instance = $item->workflowActif;
        if (! $instance) {
            return response()->json(['message' => 'Aucun circuit actif.', 'code' => 'sans_circuit'], 422);
        }

        $resultat = $this->idempotence->executer($request, $type.'.decision.'.$item->id, function () use ($request, $config, $item, $instance, $data, $type) {
            $this->assertVersion($request, $item);
            try {
                // Contrôles préalables (RIB), moteur et effets métier : service partagé par tous les points d'entrée.
                app(\App\Services\Workflow\DecisionMetierService::class)->decider($item, $request->user(), $data);
            } catch (RuntimeException $e) {
                return ['status' => 422, 'body' => ['message' => $e->getMessage(), 'code' => 'credits_ou_transition']];
            }

            return [
                'status' => 200,
                'body' => [
                    'message' => 'Décision enregistrée.',
                    'code' => 'decision',
                    'item' => (new EtapeDepenseResource($this->recharger($type, $item)))->resolve($request),
                ],
            ];
        });

        return response()->json($resultat['body'], $resultat['status']);
    }

    public function constatRejetInstrument(Request $request, int $id): JsonResponse
    {
        $paiement = Paiement::findOrFail($id);
        $this->authorize('contrepasser', $paiement);
        $data = $request->validate([
            'motif' => ['required', 'string', 'max:500'],
            'preuve' => ['nullable', 'string', 'max:500'],
        ]);

        $resultat = $this->idempotence->executer($request, 'paiement.rejet-instrument.'.$paiement->id, function () use ($request, $paiement, $data) {
            $this->assertVersion($request, $paiement);
            try {
                app(PaiementContrepassationService::class)->constaterRejetInstrument(
                    $paiement,
                    $request->user(),
                    $data['motif'],
                    $data['preuve'] ?? null,
                );
            } catch (RuntimeException $e) {
                return ['status' => 422, 'body' => ['message' => $e->getMessage(), 'code' => 'credits_ou_transition']];
            }

            return [
                'status' => 200,
                'body' => [
                    'message' => 'Rejet d’instrument constaté. Le dossier n’est pas effacé ; le reliquat d’ordonnancement est rétabli.',
                    'code' => 'instrument_rejete',
                    'item' => (new EtapeDepenseResource($this->recharger('paiement', $paiement)))->resolve($request),
                ],
            ];
        });

        return response()->json($resultat['body'], $resultat['status']);
    }

    public function pdf(string $type, int $id, DocumentPdf $pdf): Response
    {
        $item = $this->trouver($type, $id);
        $this->authorize('view', $item);

        return $pdf->etapeDepense($item, $this->config($type))->stream("{$type}_{$item->numero}.pdf");
    }

    private function config(string $type): array
    {
        abort_unless(Etapes::existe($type), 404);

        return Etapes::get($type);
    }

    private function trouver(string $type, int $id): Model
    {
        return $this->config($type)['model']::findOrFail($id);
    }

    private function filtrerExercice(Builder $query, string $type, int $exerciceId): void
    {
        if ($exerciceId <= 0) {
            return;
        }

        match ($type) {
            'liquidation' => $query->whereHas('engagement', fn ($q) => $q->where('exercice_id', $exerciceId)),
            'ordonnancement' => $query->whereHas('liquidation.engagement', fn ($q) => $q->where('exercice_id', $exerciceId)),
            'paiement' => $query->whereHas('ordonnancement.liquidation.engagement', fn ($q) => $q->where('exercice_id', $exerciceId)),
            default => null,
        };
    }

    private function montantEtape(string $type, Model $parent, Request $request, ?int $ignorerLiquidationId = null): float
    {
        if ($type === 'liquidation' && $parent instanceof Engagement) {
            $restant = $parent->montantRestantLiquidation($ignorerLiquidationId);
            if ($restant <= 0) {
                throw new RuntimeException('Le montant de l\'engagement est entièrement couvert par des liquidations.');
            }

            $montant = $request->filled('montant')
                ? (float) $request->input('montant')
                : $restant;

            $decomp = app(\App\Services\Depense\LiquidationMontantsService::class)
                ->depuisRequete($request, $montant);
            $montant = $decomp['montant'];

            $this->quotas->assertPeutLiquider($parent, $montant, $ignorerLiquidationId);

            return $montant;
        }

        if ($type === 'paiement' && $parent instanceof Ordonnancement) {
            $restant = $this->quotas->montantRestantPaiement($parent);
            if ($restant <= 0) {
                throw new RuntimeException('L\'ordonnancement est entièrement couvert par des paiements.');
            }
            $montant = $request->filled('montant')
                ? (float) $request->input('montant')
                : $restant;
            $this->quotas->assertPeutPayer($parent, $montant);

            if ($montant + 0.005 < $restant && ! $request->filled('motif_partiel')) {
                throw new RuntimeException('Un motif est obligatoire pour un paiement partiel (reliquat d’ordonnancement).');
            }

            return $montant;
        }

        return (float) ($parent->montant_total ?? $parent->montant);
    }

    private function racine(string $type, Model $parent): Engagement
    {
        return match ($type) {
            'liquidation' => $parent,
            'ordonnancement' => $parent->engagement,
            'paiement' => $parent->liquidation->engagement,
        };
    }

    private function assertLignesActives(Engagement $racine): void
    {
        $racine->loadMissing('imputations.ligneBudgetaire');
        foreach ($racine->imputations as $imputation) {
            $ligne = $imputation->ligneBudgetaire;
            if (! $ligne || ! $ligne->estDisponible()) {
                throw new RuntimeException('Une ligne budgétaire imputée est inactive ou archivée.');
            }
        }
    }

    private function champsModele(array $data, string $type): array
    {
        $autorises = match ($type) {
            'liquidation' => ['service_fait', 'date_liquidation', 'numero_facture', 'date_facture', 'fournisseur', 'numero_tranche', 'montant_brut', 'montant_retenues', 'montant_net'],
            'ordonnancement' => ['date_ordonnancement'],
            'paiement' => ['mode_paiement', 'devise', 'taux_change', 'reference_bancaire', 'rib', 'motif_partiel', 'date_paiement'],
            default => [],
        };

        return array_intersect_key($data, array_flip($autorises));
    }

    private function completerLiquidation(Liquidation $item, Request $request, Engagement $parent, bool $nouveau): void
    {
        if (! $item->numero_tranche) {
            $rang = Liquidation::query()
                ->where('engagement_id', $parent->id)
                ->whereNotIn('statut', ['rejete', 'annule'])
                ->orderBy('id')
                ->pluck('id')
                ->search($item->id);
            $item->update(['numero_tranche' => is_int($rang) ? $rang + 1 : 1]);
        }

        if ($nouveau || $request->filled('service_fait') || $request->filled('piece_type')) {
            app(ServiceFaitService::class)->enregistrer($item, $request->all(), $request->user());
        }

        if ($request->filled('numero_facture')) {
            $item->piecesLiquidation()->updateOrCreate(
                ['type' => 'facture', 'reference' => $request->string('numero_facture')->toString()],
                ['date_piece' => $request->input('date_facture')],
            );
        }
    }

    private function recharger(string $type, Model $item): Model
    {
        $relations = [$this->config($type)['parent_type'], 'workflowActif'];
        if ($type === 'paiement') {
            $relations[] = 'rejets';
        }

        return $item->fresh()->load($relations);
    }

    private function assertVersion(Request $request, Model $item): void
    {
        $attendu = $request->header('If-Match');
        if ($attendu === null || $attendu === '') {
            return;
        }

        if ($attendu !== $item->updated_at?->toIso8601String()) {
            abort(response()->json([
                'message' => 'La pièce a été modifiée. Rechargez avant d’agir.',
                'code' => 'conflict',
            ], 409));
        }
    }

    private function regles(array $config, string $type, Request $request): array
    {
        $regles = [
            'parent' => ['required', 'integer'],
            'date' => ['nullable', 'date'],
        ];
        if ($type === 'liquidation') {
            $regles['montant'] = ['nullable', 'numeric', 'min:0.01'];
            $regles['service_fait'] = ['nullable', 'string', 'max:1000'];
            $regles['service_fait_type'] = ['nullable', Rule::in(array_keys(\App\Models\ServiceFait::TYPES))];
            $regles['service_fait_reference'] = ['nullable', 'string', 'max:100'];
            $regles['service_fait_date'] = ['nullable', 'date'];
            $regles['piece_type'] = ['nullable', Rule::in(array_keys(\App\Models\LiquidationPiece::TYPES))];
            $regles['piece_reference'] = ['nullable', 'string', 'max:100'];
            $regles['piece_date'] = ['nullable', 'date'];
            $regles['numero_facture'] = ['nullable', 'string', 'max:100'];
            $regles['date_facture'] = ['nullable', 'date'];
            $regles['fournisseur'] = ['nullable', 'string', 'max:180'];
            $regles['montant_brut'] = ['nullable', 'numeric', 'min:0'];
            $regles['montant_retenues'] = ['nullable', 'numeric', 'min:0'];
            $regles['montant_net'] = ['nullable', 'numeric', 'min:0'];
        }
        if ($type === 'paiement') {
            $regles['montant'] = ['nullable', 'numeric', 'min:0.01'];
            $regles['devise'] = ['required', Rule::in(array_keys(DevisePaiementService::DEVISES))];
            $regles['taux_change'] = ['nullable', 'numeric', 'min:0.000001'];
            $mode = $request->input('mode_paiement');
            $regles['mode_paiement'] = ['required', Rule::in(['virement', 'cheque', 'especes'])];
            $regles['reference_bancaire'] = [$mode === 'cheque' ? 'required' : 'nullable', 'string', 'max:1000'];
            $regles['rib'] = [$mode === 'virement' ? 'required' : 'nullable', 'string', 'max:64'];
            $regles['motif_partiel'] = ['nullable', 'string', 'max:500'];
        }

        return $regles;
    }
}
