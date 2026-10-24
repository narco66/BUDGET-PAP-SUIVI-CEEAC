<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\Ged\DocumentStatut;
use App\Enums\Ged\NiveauConfidentialite;
use App\Http\Controllers\Controller;
use App\Http\Requests\Ged\GedDocumentRequest;
use App\Models\Ged\GedCategorie;
use App\Models\Ged\GedDocument;
use App\Models\Ged\GedDossier;
use App\Models\Ged\GedRechercheSauvegardee;
use App\Models\Ged\GedSignature;
use App\Models\Ged\GedTypeDocument;
use App\Models\User;
use App\Services\Api\IdempotenceActionService;
use App\Services\Ged\GedAccessService;
use App\Services\Ged\GedDashboardService;
use App\Services\Ged\GedDocumentService;
use App\Services\Ged\GedJournalService;
use App\Services\Ged\GedSearchService;
use App\Services\Ged\GedSignatureService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Spatie\Permission\Exceptions\PermissionDoesNotExist;
use Symfony\Component\HttpKernel\Exception\HttpException;

/** GED session/SPA : métadonnées, dépôt, signatures (pas de binaire en réponse). */
class GedApiController extends Controller
{
    public function __construct(
        private readonly GedAccessService $acces,
        private readonly GedDashboardService $dashboard,
        private readonly GedDocumentService $documents,
        private readonly GedJournalService $journal,
        private readonly GedSignatureService $signatures,
        private readonly GedSearchService $recherche,
        private readonly IdempotenceActionService $idempotence,
    ) {}

    public function hub(Request $request): JsonResponse
    {
        abort_unless($request->user()?->can('ged.voir'), 403);
        $user = $request->user();
        $stats = $this->dashboard->indicateurs($user);
        $peutExporter = (bool) $user?->can('ged.exporter');

        return response()->json([
            'indicateurs' => [
                'total' => (int) $stats['total'],
                'deposes' => (int) $stats['deposes'],
                'valides' => (int) $stats['valides'],
                'signes' => (int) $stats['signes'],
                'archives' => (int) $stats['archives'],
                'confidentiels' => (int) $stats['confidentiels'],
                'expires' => (int) $stats['expires'],
                'en_validation' => (int) $stats['en_validation'],
                'en_attente_signature' => (int) $stats['en_attente_signature'],
                'volume_octets' => (int) $stats['volume_octets'],
            ],
            'par_module' => collect($stats['par_module'] ?? [])
                ->map(fn ($total, $module) => ['module' => (string) $module, 'total' => (int) $total])
                ->values()
                ->all(),
            'recents' => collect($stats['recents'] ?? [])->map(fn (GedDocument $doc) => $this->resume($doc))->values()->all(),
            'peut_deposer' => (bool) $user?->can('ged.creer'),
            'peut_signer' => (bool) $user?->can('ged.signer'),
            'peut_exporter' => $peutExporter,
            'href_depot' => $this->href('ged.documents.create'),
            'href_signatures' => $this->href('ged.signatures.index'),
            'href_recherche' => $this->href('ged.recherche'),
            'exports' => $peutExporter
                ? [['format' => 'excel-registre', 'href' => $this->href('ged.exports.documents.excel')]]
                : [],
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        abort_unless($request->user()?->can('ged.voir'), 403);

        $query = $this->documents->scopeAccessible(GedDocument::query(), $request->user())
            ->with(['categorie', 'structure', 'exercice'])
            ->when(! $request->boolean('archives'), fn ($q) => $q->where('est_archive', false))
            ->when($request->filled('module'), fn ($q) => $q->where('module_source', $request->string('module')))
            ->when($request->filled('exercice'), fn ($q) => $q->where('exercice_id', $request->integer('exercice')))
            ->when($request->filled('statut'), fn ($q) => $q->where('statut', $request->string('statut')))
            ->when($request->filled('q'), fn ($q) => $q->where(function ($inner) use ($request) {
                $terme = '%'.$request->string('q').'%';
                $inner->where('titre', 'like', $terme)->orWhere('reference', 'like', $terme);
            }))
            ->orderByDesc('date_depot');

        $page = $query->paginate(min(50, max(1, (int) $request->integer('per_page', $request->integer('limit', 30)))));

        return response()->json([
            'data' => $page->getCollection()->map(fn (GedDocument $doc) => $this->resume($doc))->values()->all(),
            'meta' => [
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'total' => $page->total(),
            ],
        ]);
    }

    public function show(Request $request, GedDocument $document): JsonResponse
    {
        abort_unless($request->user()?->can('ged.voir'), 403);
        abort_unless($this->acces->peutConsulter($request->user(), $document), 403);

        $this->journal->enregistrer('consultation', $document, $request->user());
        $document->load(['categorie', 'typeDocument', 'dossier', 'structure', 'exercice', 'versions', 'signatures.signataire']);

        return response()->json([
            'data' => $this->fiche($document, $request),
        ]);
    }

    public function options(Request $request): JsonResponse
    {
        abort_unless($request->user()?->can('ged.voir'), 403);

        return response()->json([
            'categories' => GedCategorie::query()->where('actif', true)->orderBy('libelle')->get(['id', 'code', 'libelle']),
            'types' => GedTypeDocument::query()->where('actif', true)->orderBy('libelle')->get(['id', 'code', 'libelle', 'categorie_id']),
            'dossiers' => GedDossier::query()->orderBy('titre')->get(['id', 'titre', 'reference']),
            'niveaux' => collect(NiveauConfidentialite::options())->map(fn ($libelle, $code) => [
                'code' => $code,
                'libelle' => $libelle,
            ])->values()->all(),
            'signataires' => $request->user()?->can('ged.signer') || $request->user()?->can('ged.admin')
                ? User::query()->permission('ged.signer')->where('actif', true)->orderBy('name')->limit(100)->get(['id', 'name', 'email'])
                : [],
        ]);
    }

    public function store(GedDocumentRequest $request): JsonResponse
    {
        $this->authorize('create', GedDocument::class);
        $fichier = $request->file('fichier');
        abort_unless($fichier !== null, 422, 'Fichier requis.');

        $resultat = $this->idempotence->executer($request, 'ged.deposer', function () use ($request, $fichier) {
            $donnees = $request->only([
                'titre', 'description', 'categorie_id', 'type_document_id', 'dossier_id',
                'module_source', 'niveau_confidentialite', 'date_document', 'date_expiration',
            ]);
            if ($request->filled('tags')) {
                $donnees['tags'] = array_map('trim', explode(',', (string) $request->input('tags')));
            }
            if (! empty($donnees['type_document_id'])) {
                $donnees['type_code'] = GedTypeDocument::query()->whereKey($donnees['type_document_id'])->value('code') ?? 'DOC';
            }

            $document = $this->documents->deposer($fichier, $donnees, $request->user());

            return [
                'status' => 201,
                'body' => [
                    'message' => 'Document déposé.',
                    'code' => 'depose',
                    'data' => $this->fiche(
                        $document->fresh(['categorie', 'typeDocument', 'dossier', 'structure', 'exercice', 'versions', 'signatures.signataire']) ?? $document,
                        $request,
                    ),
                ],
            ];
        });

        return response()->json($resultat['body'], $resultat['status']);
    }

    public function signatures(Request $request): JsonResponse
    {
        abort_unless($request->user()?->can('ged.voir'), 403);
        $user = $request->user();

        $query = GedSignature::query()
            ->with(['document', 'signataire'])
            ->when(! $user?->can('ged.admin'), function ($q) use ($user) {
                $q->where(function ($inner) use ($user) {
                    $inner->where('signataire_id', $user?->id)
                        ->orWhereHas('document', fn ($d) => $d->where('created_by', $user?->id));
                });
            })
            ->when($request->filled('statut'), fn ($q) => $q->where('statut', $request->string('statut')))
            ->latest();

        $page = $query->paginate(min(50, max(1, (int) $request->integer('per_page', 30))));
        $lignes = $page->getCollection()->filter(function (GedSignature $s) use ($user) {
            return $s->document && $this->acces->peutConsulter($user, $s->document);
        })->map(fn (GedSignature $s) => $this->formatSignature($s, $user))->values()->all();

        return response()->json([
            'data' => $lignes,
            'meta' => [
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'total' => $page->total(),
            ],
            'peut_demander' => (bool) $user?->can('ged.signer') || (bool) $user?->can('ged.admin'),
        ]);
    }

    public function demanderSignature(Request $request, GedDocument $document): JsonResponse
    {
        abort_unless($request->user()?->can('sign', $document), 403);
        abort_unless($this->acces->peutConsulter($request->user(), $document), 403);
        $valide = $request->validate([
            'signataire_id' => ['required', 'exists:users,id'],
        ]);
        $signataire = User::query()->findOrFail($valide['signataire_id']);
        abort_unless($signataire->can('ged.signer'), 422, 'Le destinataire ne peut pas signer.');

        $resultat = $this->idempotence->executer($request, 'ged.signature.demander.'.$document->id, function () use ($request, $document, $signataire) {
            $signature = $this->signatures->demander($document, $request->user(), $signataire);

            return [
                'status' => 201,
                'body' => [
                    'message' => 'Demande de signature envoyée.',
                    'code' => 'demande_signature',
                    'data' => $this->formatSignature($signature->load(['document', 'signataire']), $request->user()),
                ],
            ];
        });

        return response()->json($resultat['body'], $resultat['status']);
    }

    public function signer(Request $request, GedSignature $signature): JsonResponse
    {
        abort_unless($request->user()?->can('ged.signer'), 403);
        abort_unless($signature->document && $this->acces->peutConsulter($request->user(), $signature->document), 403);

        $resultat = $this->idempotence->executer($request, 'ged.signature.signer.'.$signature->id, function () use ($request, $signature) {
            try {
                $signee = $this->signatures->signer($signature, $request->user());
            } catch (HttpException $e) {
                return [
                    'status' => $e->getStatusCode(),
                    'body' => ['message' => $e->getMessage() ?: 'Signature refusée.', 'code' => 'transition_invalide'],
                ];
            }

            return [
                'status' => 200,
                'body' => [
                    'message' => 'Document signé.',
                    'code' => 'signe',
                    'data' => $this->formatSignature($signee->load(['document', 'signataire']), $request->user()),
                ],
            ];
        });

        return response()->json($resultat['body'], $resultat['status']);
    }

    public function rejeterSignature(Request $request, GedSignature $signature): JsonResponse
    {
        abort_unless($request->user()?->can('ged.signer'), 403);
        abort_unless($signature->document && $this->acces->peutConsulter($request->user(), $signature->document), 403);
        $valide = $request->validate(['motif' => ['required', 'string', 'max:500']]);

        $resultat = $this->idempotence->executer($request, 'ged.signature.rejeter.'.$signature->id, function () use ($request, $signature, $valide) {
            try {
                $rejetee = $this->signatures->rejeter($signature, $request->user(), $valide['motif']);
            } catch (HttpException $e) {
                return [
                    'status' => $e->getStatusCode(),
                    'body' => ['message' => $e->getMessage() ?: 'Rejet refusé.', 'code' => 'transition_invalide'],
                ];
            }

            return [
                'status' => 200,
                'body' => [
                    'message' => 'Signature rejetée.',
                    'code' => 'rejetee',
                    'data' => $this->formatSignature($rejetee->load(['document', 'signataire']), $request->user()),
                ],
            ];
        });

        return response()->json($resultat['body'], $resultat['status']);
    }

    public function rechercher(Request $request): JsonResponse
    {
        abort_unless($this->peut($request, 'ged.rechercher') || $this->peut($request, 'ged.voir'), 403);
        $user = $request->user();
        $filtres = $request->except(['page', 'per_page', 'limit']);
        $page = $this->recherche->rechercher(
            $filtres,
            $user,
            min(50, max(1, (int) $request->integer('per_page', 20))),
        );

        return response()->json([
            'data' => $page->getCollection()->map(fn (GedDocument $doc) => $this->resume($doc))->values()->all(),
            'meta' => [
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'total' => $page->total(),
            ],
            'filtres' => $filtres,
            'options' => [
                'statuts' => collect(DocumentStatut::options())->map(fn ($libelle, $code) => [
                    'code' => $code,
                    'libelle' => $libelle,
                ])->values()->all(),
                'niveaux' => collect(NiveauConfidentialite::options())->map(fn ($libelle, $code) => [
                    'code' => $code,
                    'libelle' => $libelle,
                ])->values()->all(),
                'categories' => GedCategorie::query()->where('actif', true)->orderBy('libelle')->get(['id', 'libelle']),
                'types' => GedTypeDocument::query()->where('actif', true)->orderBy('libelle')->get(['id', 'libelle']),
            ],
            'sauvegardees' => GedRechercheSauvegardee::query()
                ->where('utilisateur_id', $user->id)
                ->orderBy('nom')
                ->get()
                ->map(fn (GedRechercheSauvegardee $rs) => [
                    'id' => $rs->id,
                    'nom' => $rs->nom,
                    'filtres' => $rs->filtres ?? [],
                    'par_defaut' => (bool) $rs->par_defaut,
                ])
                ->values()
                ->all(),
        ]);
    }

    public function sauvegarderRecherche(Request $request): JsonResponse
    {
        abort_unless($this->peut($request, 'ged.rechercher') || $this->peut($request, 'ged.voir'), 403);
        $valide = $request->validate([
            'nom' => ['required', 'string', 'max:120'],
            'par_defaut' => ['sometimes', 'boolean'],
            'filtres' => ['nullable', 'array'],
        ]);

        $resultat = $this->idempotence->executer($request, 'ged.recherche.sauvegarder', function () use ($request, $valide) {
            $user = $request->user();
            if ($request->boolean('par_defaut')) {
                GedRechercheSauvegardee::query()->where('utilisateur_id', $user->id)->update(['par_defaut' => false]);
            }

            $item = GedRechercheSauvegardee::query()->updateOrCreate(
                ['utilisateur_id' => $user->id, 'nom' => $valide['nom']],
                [
                    'filtres' => $valide['filtres'] ?? [],
                    'par_defaut' => $request->boolean('par_defaut'),
                ],
            );

            return [
                'status' => 200,
                'body' => [
                    'message' => 'Recherche sauvegardée.',
                    'data' => [
                        'id' => $item->id,
                        'nom' => $item->nom,
                        'filtres' => $item->filtres ?? [],
                        'par_defaut' => (bool) $item->par_defaut,
                    ],
                ],
            ];
        });

        return response()->json($resultat['body'], $resultat['status']);
    }

    public function supprimerRecherche(Request $request, GedRechercheSauvegardee $recherche): JsonResponse
    {
        abort_unless($recherche->utilisateur_id === $request->user()?->id, 403);
        $recherche->delete();

        return response()->json(['message' => 'Recherche supprimée.']);
    }

    /** @return array<string, mixed> */
    private function fiche(GedDocument $document, Request $request): array
    {
        $peutTelecharger = $this->acces->peutTelecharger($request->user(), $document);

        return array_merge($this->resume($document), [
            'description' => $document->description,
            'mime_type' => $document->mime_type,
            'taille' => $document->taille,
            'hash_sha256' => $document->hash_sha256,
            'version_courante' => $document->version_courante,
            'date_document' => $document->date_document?->toDateString(),
            'date_expiration' => $document->date_expiration?->toDateString(),
            'categorie' => $document->categorie?->libelle,
            'type' => $document->typeDocument?->libelle,
            'dossier' => $document->dossier?->titre,
            'structure' => $document->structure?->libelle ?? $document->structure?->intitule,
            'exercice' => $document->exercice?->annee,
            'versions' => $document->versions->map(fn ($v) => [
                'numero' => $v->numero,
                'statut' => $v->statut,
                'commentaire' => $v->commentaire,
                'created_at' => $v->created_at?->toIso8601String(),
            ])->values()->all(),
            'signatures' => $document->signatures
                ->map(fn (GedSignature $s) => $this->formatSignature($s, $request->user()))
                ->values()
                ->all(),
            'peut_telecharger' => $peutTelecharger,
            'peut_demander_signature' => (bool) $request->user()?->can('sign', $document),
            'href_fiche' => $this->href('ged.documents.show', $document),
            'href_telechargement' => $peutTelecharger ? $this->href('ged.documents.download', $document) : null,
            'href_edition' => $request->user()?->can('ged.modifier') ? $this->href('ged.documents.edit', $document) : null,
        ]);
    }

    /** @return array<string, mixed> */
    private function formatSignature(GedSignature $signature, ?User $user): array
    {
        $doc = $signature->document;

        return [
            'id' => $signature->id,
            'statut' => $signature->statut,
            'niveau' => $signature->niveau,
            'provider' => $signature->provider,
            'signataire' => $signature->signataire?->name,
            'signataire_id' => $signature->signataire_id,
            'document_uuid' => $doc?->uuid,
            'document_reference' => $doc?->reference,
            'document_titre' => $doc?->titre,
            'date_demande' => $signature->date_demande?->toIso8601String(),
            'date_signature' => $signature->date_signature?->toIso8601String(),
            'peut_signer' => $user !== null
                && $signature->statut === 'en_attente'
                && (int) $signature->signataire_id === (int) $user->id
                && $user->can('ged.signer'),
        ];
    }

    /** @return array<string, mixed> */
    private function resume(GedDocument $doc): array
    {
        return [
            'uuid' => $doc->uuid,
            'reference' => $doc->reference,
            'titre' => $doc->titre,
            'module_source' => $doc->module_source,
            'statut' => $doc->statut?->value ?? $doc->statut,
            'niveau_confidentialite' => $doc->niveau_confidentialite?->value ?? null,
            'est_signe' => (bool) $doc->est_signe,
            'est_archive' => (bool) $doc->est_archive,
            'date_depot' => $doc->date_depot?->toIso8601String(),
        ];
    }

    private function href(string $name, mixed ...$params): string
    {
        $legacy = rtrim((string) config('spa.legacy_url', config('app.url')), '/');

        return $legacy.route($name, $params, false);
    }

    private function peut(Request $request, string $ability): bool
    {
        try {
            return (bool) $request->user()?->can($ability);
        } catch (PermissionDoesNotExist) {
            return false;
        }
    }
}
