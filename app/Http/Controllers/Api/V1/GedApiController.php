<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Ged\GedDocument;
use App\Services\Ged\GedAccessService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Resources\Json\JsonResource;

/** Endpoints métadonnées GED (I-P3 — sans téléchargement binaire). */
class GedApiController extends Controller
{
    public function __construct(private readonly GedAccessService $acces) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        abort_unless($request->user()->can('ged.voir'), 403);

        $query = GedDocument::query()
            ->with(['categorie', 'structure', 'exercice'])
            ->when(! $request->boolean('archives'), fn ($q) => $q->where('est_archive', false))
            ->when($request->filled('module'), fn ($q) => $q->where('module_source', $request->module))
            ->when($request->filled('exercice'), fn ($q) => $q->where('exercice_id', $request->exercice))
            ->when($request->filled('q'), fn ($q) => $q->where('titre', 'like', '%'.$request->q.'%'))
            ->orderByDesc('date_depot');

        $items = $query->paginate(min(50, max(1, (int) $request->integer('limit', 30))));

        return JsonResource::collection($items->through(fn (GedDocument $doc) => [
            'uuid' => $doc->uuid,
            'reference' => $doc->reference,
            'titre' => $doc->titre,
            'module_source' => $doc->module_source,
            'statut' => $doc->statut?->value ?? $doc->statut,
            'niveau_confidentialite' => $doc->niveau_confidentialite?->value ?? null,
            'est_signe' => $doc->est_signe,
            'est_archive' => $doc->est_archive,
            'date_depot' => $doc->date_depot?->toIso8601String(),
        ]));
    }

    public function show(Request $request, GedDocument $document): JsonResource
    {
        abort_unless($request->user()->can('ged.voir'), 403);
        abort_unless($this->acces->peutConsulter($request->user(), $document), 403);

        $document->load(['categorie', 'structure', 'exercice', 'versions']);

        return new JsonResource([
            'uuid' => $document->uuid,
            'reference' => $document->reference,
            'titre' => $document->titre,
            'description' => $document->description,
            'module_source' => $document->module_source,
            'mime_type' => $document->mime_type,
            'taille' => $document->taille,
            'hash_sha256' => $document->hash_sha256,
            'version_courante' => $document->version_courante,
            'est_signe' => $document->est_signe,
            'est_archive' => $document->est_archive,
            'date_document' => $document->date_document?->toDateString(),
            'date_depot' => $document->date_depot?->toIso8601String(),
        ]);
    }
}
