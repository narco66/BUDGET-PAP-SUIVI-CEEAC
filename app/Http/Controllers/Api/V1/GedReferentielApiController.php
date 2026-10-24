<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Ged\GedCategorie;
use App\Models\Ged\GedConservationRule;
use App\Models\Ged\GedDocument;
use App\Models\Ged\GedDossier;
use App\Models\Ged\GedJournal;
use App\Models\Ged\GedTypeDocument;
use App\Models\Ged\GedVersion;
use App\Models\Ged\GedWorkflow;
use App\Services\Api\IdempotenceActionService;
use App\Services\Ged\GedAntivirusService;
use App\Services\Ged\GedSearchService;
use App\Services\Ged\GedSignatureService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Spatie\Permission\Exceptions\PermissionDoesNotExist;

class GedReferentielApiController extends Controller
{
    public function __construct(
        private readonly GedSearchService $recherche,
        private readonly GedAntivirusService $antivirus,
        private readonly GedSignatureService $signatures,
        private readonly IdempotenceActionService $idempotence,
    ) {}

    public function vue(Request $request, string $vue): JsonResponse
    {
        abort_unless($this->peut($request, 'ged.voir'), 403);
        abort_unless(in_array($vue, ['archives', 'partages', 'confidentiels', 'expires'], true), 404);

        if ($vue === 'partages') {
            $page = GedDocument::query()
                ->whereHas('partages', function ($q) use ($request) {
                    $q->where('partage_avec', $request->user()->id)
                        ->orWhere('structure_id', $request->user()->structure_id);
                })
                ->paginate(min(50, max(1, (int) $request->integer('per_page', 20))));
        } else {
            $filtres = $request->except(['page', 'per_page', 'limit']);
            if ($vue === 'archives') {
                $filtres['est_archive'] = 1;
            }
            if ($vue === 'confidentiels') {
                $filtres['niveau_confidentialite'] = 'confidentiel';
            }
            if ($vue === 'expires') {
                $filtres['statut'] = 'expire';
            }
            $page = $this->recherche->rechercher(
                $filtres,
                $request->user(),
                min(50, max(1, (int) $request->integer('per_page', 20))),
            );
        }

        return response()->json([
            'vue' => $vue,
            'data' => $page->getCollection()->map(fn (GedDocument $doc) => [
                'uuid' => $doc->uuid,
                'reference' => $doc->reference,
                'titre' => $doc->titre,
                'module_source' => $doc->module_source,
                'statut' => $doc->statut?->value ?? $doc->statut,
            ])->values()->all(),
            'meta' => [
                'total' => $page->total(),
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
            ],
        ]);
    }

    public function classement(Request $request): JsonResponse
    {
        abort_unless($this->peut($request, 'ged.voir'), 403);

        $categories = GedCategorie::with(['enfants'])
            ->whereNull('parent_id')
            ->orderBy('ordre')
            ->get()
            ->map(fn (GedCategorie $c) => [
                'id' => $c->id,
                'code' => $c->code,
                'libelle' => $c->libelle,
                'enfants' => $c->enfants->map(fn (GedCategorie $e) => [
                    'id' => $e->id,
                    'code' => $e->code,
                    'libelle' => $e->libelle,
                ])->values()->all(),
            ])
            ->values()
            ->all();

        $dossiers = GedDossier::query()
            ->whereNull('parent_id')
            ->orderBy('reference')
            ->get(['id', 'reference', 'titre'])
            ->map(fn (GedDossier $d) => $d->only(['id', 'reference', 'titre']))
            ->values()
            ->all();

        return response()->json([
            'data' => [
                'categories' => $categories,
                'dossiers' => $dossiers,
                'codification' => 'CEEAC/GED/{MODULE}/{TYPE}/{ANNEE}/{NUMERO}',
            ],
        ]);
    }

    public function categories(Request $request): JsonResponse
    {
        abort_unless($this->peut($request, 'ged.admin'), 403);
        $page = GedCategorie::with('parent')->orderBy('ordre')->paginate(20);

        return response()->json([
            'data' => $page->getCollection()->map(fn (GedCategorie $c) => [
                'id' => $c->id,
                'code' => $c->code,
                'libelle' => $c->libelle,
                'parent' => $c->parent?->libelle,
            ])->values()->all(),
            'meta' => ['total' => $page->total(), 'current_page' => $page->currentPage()],
            'peut_creer' => true,
        ]);
    }

    public function storeCategorie(Request $request): JsonResponse
    {
        abort_unless($this->peut($request, 'ged.admin'), 403);
        $valide = $request->validate([
            'code' => ['required', 'string', 'max:50', 'unique:ged_categories,code'],
            'libelle' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'parent_id' => ['nullable', 'exists:ged_categories,id'],
        ]);

        $resultat = $this->idempotence->executer($request, 'ged.categorie.creer', function () use ($request, $valide) {
            $item = GedCategorie::create($valide + ['created_by' => $request->user()->id]);

            return [
                'status' => 201,
                'body' => ['message' => 'Catégorie créée.', 'data' => $item->only(['id', 'code', 'libelle'])],
            ];
        });

        return response()->json($resultat['body'], $resultat['status']);
    }

    public function types(Request $request): JsonResponse
    {
        abort_unless($this->peut($request, 'ged.admin'), 403);
        $page = GedTypeDocument::with('categorie')->paginate(20);

        return response()->json([
            'data' => $page->getCollection()->map(fn (GedTypeDocument $t) => [
                'id' => $t->id,
                'code' => $t->code,
                'libelle' => $t->libelle,
                'categorie' => $t->categorie?->libelle,
            ])->values()->all(),
            'categories' => GedCategorie::query()->where('actif', true)->orderBy('libelle')->get(['id', 'libelle']),
            'meta' => ['total' => $page->total()],
            'peut_creer' => true,
        ]);
    }

    public function storeType(Request $request): JsonResponse
    {
        abort_unless($this->peut($request, 'ged.admin'), 403);
        $valide = $request->validate([
            'code' => ['required', 'string', 'max:50', 'unique:ged_types_documents,code'],
            'libelle' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'categorie_id' => ['nullable', 'exists:ged_categories,id'],
        ]);

        $resultat = $this->idempotence->executer($request, 'ged.type.creer', function () use ($request, $valide) {
            $item = GedTypeDocument::create($valide + ['created_by' => $request->user()->id]);

            return [
                'status' => 201,
                'body' => ['message' => 'Type de document créé.', 'data' => $item->only(['id', 'code', 'libelle'])],
            ];
        });

        return response()->json($resultat['body'], $resultat['status']);
    }

    public function dossiers(Request $request): JsonResponse
    {
        abort_unless($this->peut($request, 'ged.voir'), 403);
        $page = GedDossier::with(['categorie', 'structure'])->paginate(20);

        return response()->json([
            'data' => $page->getCollection()->map(fn (GedDossier $d) => [
                'id' => $d->id,
                'reference' => $d->reference,
                'titre' => $d->titre,
                'structure' => $d->structure?->libelle,
            ])->values()->all(),
            'meta' => ['total' => $page->total()],
            'peut_creer' => $this->peut($request, 'ged.creer'),
        ]);
    }

    public function storeDossier(Request $request): JsonResponse
    {
        abort_unless($this->peut($request, 'ged.creer'), 403);
        $valide = $request->validate([
            'reference' => ['required', 'string', 'max:100', 'unique:ged_dossiers,reference'],
            'titre' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'categorie_id' => ['nullable', 'exists:ged_categories,id'],
            'parent_id' => ['nullable', 'exists:ged_dossiers,id'],
            'module_source' => ['nullable', 'string', 'max:50'],
        ]);

        $resultat = $this->idempotence->executer($request, 'ged.dossier.creer', function () use ($request, $valide) {
            $item = GedDossier::create($valide + ['created_by' => $request->user()->id]);

            return [
                'status' => 201,
                'body' => ['message' => 'Dossier créé.', 'data' => $item->only(['id', 'reference', 'titre'])],
            ];
        });

        return response()->json($resultat['body'], $resultat['status']);
    }

    public function versions(Request $request): JsonResponse
    {
        abort_unless($this->peut($request, 'ged.voir'), 403);
        $page = GedVersion::with(['document', 'auteur'])->latest()->paginate(20);

        return response()->json([
            'data' => $page->getCollection()->map(fn (GedVersion $v) => [
                'id' => $v->id,
                'numero' => $v->numero,
                'nom_original' => $v->nom_original,
                'hash' => Str::limit((string) $v->hash_sha256, 12, ''),
                'auteur' => $v->auteur?->name,
                'created_at' => $v->created_at?->toIso8601String(),
                'document_uuid' => $v->document?->uuid,
                'document_reference' => $v->document?->reference,
            ])->values()->all(),
            'meta' => ['total' => $page->total()],
        ]);
    }

    public function workflows(Request $request): JsonResponse
    {
        abort_unless($this->peut($request, 'ged.admin'), 403);
        $page = GedWorkflow::with('etapes')->paginate(20);

        return response()->json([
            'data' => $page->getCollection()->map(fn (GedWorkflow $wf) => [
                'id' => $wf->id,
                'code' => $wf->code,
                'libelle' => $wf->libelle,
                'etapes' => $wf->etapes->map(fn ($e) => [
                    'ordre' => $e->ordre,
                    'libelle' => $e->libelle,
                ])->values()->all(),
            ])->values()->all(),
            'meta' => ['total' => $page->total()],
        ]);
    }

    public function conservation(Request $request): JsonResponse
    {
        abort_unless($this->peut($request, 'ged.admin'), 403);
        $page = GedConservationRule::with(['typeDocument', 'categorie'])->paginate(20);

        return response()->json([
            'data' => $page->getCollection()->map(fn (GedConservationRule $r) => [
                'id' => $r->id,
                'cible' => $r->typeDocument?->libelle ?? $r->categorie?->libelle,
                'duree_annees' => $r->duree_annees,
                'sort_final' => $r->sort_final,
                'alerte_jours_avant' => $r->alerte_jours_avant,
            ])->values()->all(),
            'meta' => ['total' => $page->total()],
        ]);
    }

    public function journal(Request $request): JsonResponse
    {
        abort_unless($this->peut($request, 'ged.consulter_journal'), 403);
        $page = GedJournal::with(['document', 'utilisateur'])->latest()->paginate(30);

        return response()->json([
            'data' => $page->getCollection()->map(fn (GedJournal $j) => [
                'id' => $j->id,
                'action' => $j->action,
                'document_uuid' => $j->document?->uuid,
                'document_reference' => $j->document?->reference,
                'utilisateur' => $j->utilisateur?->name,
                'adresse_ip' => $j->adresse_ip,
                'created_at' => $j->created_at?->toIso8601String(),
            ])->values()->all(),
            'meta' => ['total' => $page->total()],
        ]);
    }

    public function parametres(Request $request): JsonResponse
    {
        abort_unless($this->peut($request, 'ged.admin'), 403);

        return response()->json([
            'data' => [
                'taille_max_mo' => config('ged.taille_max_mo', 10),
                'antivirus' => $this->antivirus->statut(),
                'signature' => $this->signatures->statut(),
            ],
        ]);
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
