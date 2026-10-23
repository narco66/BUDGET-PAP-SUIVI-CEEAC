<?php

namespace App\Services\Ged;

use App\Models\Ged\GedDocument;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class GedSearchService
{
    public function __construct(private GedDocumentService $documents) {}

    /**
     * @param  array<string, mixed>  $filtres
     */
    public function rechercher(array $filtres, User $user, int $parPage = 20): LengthAwarePaginator
    {
        $query = GedDocument::query()
            ->with(['categorie', 'typeDocument', 'dossier', 'createur', 'tags']);

        $query = $this->documents->scopeAccessible($query, $user);

        if (! empty($filtres['q'])) {
            $terme = '%'.$filtres['q'].'%';
            $query->where(function (Builder $q) use ($terme) {
                $q->where('titre', 'like', $terme)
                    ->orWhere('reference', 'like', $terme)
                    ->orWhere('description', 'like', $terme)
                    ->orWhere('contenu_ocr', 'like', $terme);
            });
        }

        foreach (['statut', 'niveau_confidentialite', 'module_source', 'categorie_id', 'type_document_id', 'dossier_id', 'exercice_id', 'structure_id', 'created_by'] as $champ) {
            if (! empty($filtres[$champ])) {
                $query->where($champ, $filtres[$champ]);
            }
        }

        if (! empty($filtres['tag'])) {
            $query->whereHas('tags', fn (Builder $q) => $q->where('nom', $filtres['tag']));
        }

        if (! empty($filtres['date_depot_debut'])) {
            $query->whereDate('date_depot', '>=', $filtres['date_depot_debut']);
        }
        if (! empty($filtres['date_depot_fin'])) {
            $query->whereDate('date_depot', '<=', $filtres['date_depot_fin']);
        }

        if (! empty($filtres['est_archive'])) {
            $query->where('est_archive', (bool) $filtres['est_archive']);
        }

        if (! empty($filtres['est_signe'])) {
            $query->where('est_signe', (bool) $filtres['est_signe']);
        }

        $tri = $filtres['tri'] ?? 'date_depot';
        $ordre = $filtres['ordre'] ?? 'desc';
        $query->orderBy($tri, $ordre === 'asc' ? 'asc' : 'desc');

        return $query->paginate($parPage)->withQueryString();
    }
}
