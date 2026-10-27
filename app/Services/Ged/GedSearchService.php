<?php

namespace App\Services\Ged;

use App\Enums\Ged\NiveauConfidentialite;
use App\Models\Ged\GedDocument;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class GedSearchService
{
    /** Colonnes de tri autorisées (la valeur reçue n'atteint jamais le SQL telle quelle). */
    public const TRIS = ['date_depot' => 'Date de dépôt', 'titre' => 'Titre', 'reference' => 'Référence', 'taille' => 'Taille', 'date_document' => 'Date du document', 'date_expiration' => 'Expiration'];

    /** Raccourcis du registre (indicateurs cliquables). */
    public const VUES = ['signes' => 'Signés', 'archives' => 'Archivés', 'confidentiels' => 'Confidentiels', 'expirant' => 'Expirent sous 30 j', 'mes' => 'Mes dépôts'];

    public function __construct(
        private GedDocumentService $documents,
        private GedAccessService $acces,
    ) {}

    /**
     * @param  array<string, mixed>  $filtres
     */
    public function rechercher(array $filtres, User $user, int $parPage = 20): LengthAwarePaginator
    {
        $query = $this->filtrer($filtres, $user)
            ->with(['categorie', 'typeDocument', 'dossier', 'createur', 'tags']);
        $this->vue($query, (string) ($filtres['vue'] ?? ''), $user);

        $tri = array_key_exists($filtres['tri'] ?? '', self::TRIS) ? $filtres['tri'] : 'date_depot';
        $query->orderBy($tri, ($filtres['ordre'] ?? 'desc') === 'asc' ? 'asc' : 'desc')->orderByDesc('id');

        $parPage = in_array((int) ($filtres['par_page'] ?? 0), [20, 50, 100], true) ? (int) $filtres['par_page'] : $parPage;
        $page = $query->paginate($parPage)->withQueryString();
        $this->acces->prechargerPartages($page->getCollection(), $user);

        return $page;
    }

    /**
     * Indicateurs du registre sur le périmètre accessible et les filtres courants (une seule requête).
     *
     * @param  array<string, mixed>  $filtres
     * @return array<string, int>
     */
    public function indicateurs(array $filtres, User $user): array
    {
        $confidentiels = array_map(fn ($n) => $n->value, [NiveauConfidentialite::Restreint, NiveauConfidentialite::Confidentiel, NiveauConfidentialite::TresConfidentiel]);
        $marqueurs = implode(',', array_fill(0, count($confidentiels), '?'));
        $r = $this->filtrer($filtres, $user)->toBase()->selectRaw(
            'count(*) as total, '
            .'sum(case when est_signe = ? then 1 else 0 end) as signes, '
            .'sum(case when est_archive = ? then 1 else 0 end) as archives, '
            ."sum(case when niveau_confidentialite in ({$marqueurs}) then 1 else 0 end) as confidentiels, "
            .'sum(case when date_expiration is not null and date_expiration between ? and ? then 1 else 0 end) as expirant, '
            .'sum(case when created_by = ? then 1 else 0 end) as mes, '
            .'coalesce(sum(taille), 0) as volume',
            [true, true, ...$confidentiels, now()->toDateString(), now()->addDays(30)->toDateString(), $user->id]
        )->first();

        return collect((array) $r)->map(fn ($v) => (int) $v)->all();
    }

    /** @param  array<string, mixed>  $filtres */
    private function filtrer(array $filtres, User $user): Builder
    {
        $query = $this->documents->scopeAccessible(GedDocument::query(), $user);

        if (! empty($filtres['q'])) {
            // Insensible à la casse, accents compris : ILIKE sous PostgreSQL ; LIKE ailleurs (déjà insensible à la casse).
            $terme = '%'.trim((string) $filtres['q']).'%';
            $operateur = $query->getConnection()->getDriverName() === 'pgsql' ? 'ilike' : 'like';
            $query->where(function (Builder $q) use ($terme, $operateur) {
                foreach (['titre', 'reference', 'nom_original', 'description', 'contenu_ocr'] as $i => $colonne) {
                    $q->where($colonne, $operateur, $terme, $i === 0 ? 'and' : 'or');
                }
            });
        }

        foreach (['statut', 'niveau_confidentialite', 'module_source', 'categorie_id', 'type_document_id', 'dossier_id', 'exercice_id', 'structure_id', 'created_by', 'extension'] as $champ) {
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

        return $query;
    }

    private function vue(Builder $query, string $vue, User $user): void
    {
        match ($vue) {
            'signes' => $query->where('est_signe', true),
            'archives' => $query->where('est_archive', true),
            'confidentiels' => $query->whereIn('niveau_confidentialite', [NiveauConfidentialite::Restreint, NiveauConfidentialite::Confidentiel, NiveauConfidentialite::TresConfidentiel]),
            'expirant' => $query->whereNotNull('date_expiration')->whereBetween('date_expiration', [now()->toDateString(), now()->addDays(30)->toDateString()]),
            'mes' => $query->where('created_by', $user->id),
            default => null,
        };
    }

    /**
     * Valeurs proposées par les filtres : seulement celles présentes dans le périmètre accessible.
     *
     * @return array<string, mixed>
     */
    public function options(User $user): array
    {
        $base = fn () => $this->documents->scopeAccessible(GedDocument::query(), $user);

        return [
            'modules' => $base()->whereNotNull('module_source')->distinct()->orderBy('module_source')->pluck('module_source')->all(),
            'extensions' => $base()->whereNotNull('extension')->distinct()->orderBy('extension')->pluck('extension')->all(),
            'categories' => \App\Models\Ged\GedCategorie::query()->whereIn('id', $base()->select('categorie_id'))->orderBy('libelle')->get(['id', 'libelle']),
            'types' => \App\Models\Ged\GedTypeDocument::query()->whereIn('id', $base()->select('type_document_id'))->orderBy('libelle')->get(['id', 'libelle']),
        ];
    }
}
