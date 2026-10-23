<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Activite;
use App\Models\Exercice;
use App\Models\Indicateur;
use App\Models\Pilier;
use App\Services\Rbm\RbmReferentielService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** Endpoints REST lecture du référentiel RBM/GAR (phase E8). */
class RbmApiController extends Controller
{
    public function __construct(private readonly RbmReferentielService $referentiel)
    {
    }

    public function piliers(Request $request): JsonResource
    {
        $exerciceId = $this->exerciceId($request);

        $piliers = Pilier::query()
            ->when($exerciceId, fn ($q) => $q->where('exercice_id', $exerciceId))
            ->withCount('axes')
            ->orderBy('ordre')
            ->orderBy('code')
            ->get(['id', 'exercice_id', 'code', 'intitule', 'statut', 'pap_id']);

        return JsonResource::collection($piliers);
    }

    public function matrice(Request $request): JsonResource
    {
        $exercice = $this->exercice($request);

        return JsonResource::collection(
            $this->referentiel->matriceRbm($exercice)->values()
        );
    }

    public function indicateurs(Request $request): JsonResource
    {
        $exerciceId = $this->exerciceId($request);
        abort_unless($exerciceId, 422, 'Paramètre exercice requis.');

        $activiteIds = Activite::whereHas('sousProduit.produit.axe.pilier', fn ($q) => $q->where('exercice_id', $exerciceId))->pluck('id');

        $items = Indicateur::query()
            ->where('rbm_type', Activite::class)
            ->whereIn('rbm_id', $activiteIds)
            ->with(['typeIndicateur:id,code,libelle', 'uniteMesure:id,code,symbole'])
            ->orderBy('code')
            ->paginate(min(100, (int) $request->integer('per_page', 50)));

        return JsonResource::collection($items);
    }

    private function exerciceId(Request $request): ?int
    {
        if (! $request->filled('exercice')) {
            return null;
        }

        $opt = $request->input('exercice');

        return Exercice::where('id', $opt)->orWhere('annee', $opt)->value('id');
    }

    private function exercice(Request $request): Exercice
    {
        $id = $this->exerciceId($request);
        abort_unless($id, 422, 'Paramètre exercice requis.');

        return Exercice::findOrFail($id);
    }
}
