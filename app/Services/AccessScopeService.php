<?php

namespace App\Services;

use App\Models\Structure;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Applique le périmètre d'accès (user_access_scopes) au filtrage des données.
 * L'administrateur et les détenteurs d'un scope « global » ne sont pas restreints.
 */
class AccessScopeService
{
    /** L'utilisateur a-t-il un accès global (aucune restriction de structure) ? */
    public function estGlobal(User $user): bool
    {
        return $user->estAdministrateur()
            || $user->accessScopes()->where('type', 'global')->exists()
            || $user->accessScopes()->doesntExist(); // sans scope défini = pas de restriction
    }

    public function enLectureSeule(User $user): bool
    {
        return $user->accessScopes()->where('type', 'lecture_seule')->exists();
    }

    public function limiteAuxPropresDossiers(User $user): bool
    {
        return $user->accessScopes()->where('type', 'propres_dossiers')->exists();
    }

    /** Identifiants de structures autorisées (structure ciblée + descendantes + alias liés). */
    public function structuresAutorisees(User $user): Collection
    {
        if ($this->estGlobal($user)) {
            return Structure::pluck('id'); // toutes
        }

        $cibles = $user->accessScopes()
            ->whereIn('type', ['departement', 'direction', 'service'])
            ->pluck('scope_id')->filter();

        $arbre = Structure::with('enfantsRecursifs')->get()->keyBy('id');
        $ids = collect();

        foreach ($cibles as $id) {
            $ids->push($id);
            $this->collecterDescendants($arbre->get($id), $ids);
        }

        // À défaut de périmètre structuré, on retombe sur la structure principale (+ descendants).
        if ($ids->isEmpty() && $user->structure_id) {
            $structure = $arbre->get($user->structure_id) ?? Structure::find($user->structure_id);
            if ($structure) {
                $ids->push($structure->id);
                $this->collecterDescendants($structure, $ids);
                if ($structure->structure_officielle_id) {
                    $officielle = $arbre->get($structure->structure_officielle_id)
                        ?? Structure::find($structure->structure_officielle_id);
                    if ($officielle) {
                        $ids->push($officielle->id);
                        $this->collecterDescendants($officielle, $ids);
                    }
                }
            }
        }

        return $this->etendreAvecAlias($ids->unique()->values());
    }

    public function exercicesAutorises(User $user): Collection
    {
        return $user->accessScopes()->where('type', 'exercice')->pluck('scope_id')->filter()->values();
    }

    public function dansPerimetre(User $user, ?int $structureId): bool
    {
        if ($this->estGlobal($user) || ! $structureId) {
            return true;
        }

        return $this->structuresAutorisees($user)->contains($structureId);
    }

    /** Restreint une requête Eloquent aux structures autorisées (sauf accès global). */
    public function filtrerStructures(Builder $query, User $user, string $colonne = 'structure_id'): Builder
    {
        if ($this->estGlobal($user)) {
            return $query;
        }

        return $query->whereIn($colonne, $this->structuresAutorisees($user));
    }

    /**
     * Filtre métier : structures autorisées + enregistrements sans structure (transversal).
     */
    public function filtrerStructuresMetier(Builder $query, User $user, string $colonne = 'structure_id'): Builder
    {
        if ($this->estGlobal($user)) {
            return $query;
        }

        $ids = $this->structuresAutorisees($user);

        return $query->where(function (Builder $q) use ($colonne, $ids) {
            $q->whereIn($colonne, $ids)->orWhereNull($colonne);
        });
    }

    /**
     * Restreint une liste de dossiers de la chaîne de dépense (ENG, LIQ, ORD, PAY) au périmètre organique :
     * le dossier est situé par la structure de son expression de besoin d'origine (même règle que la liste des EB),
     * les dossiers sans EB ou sans structure restant visibles (transversaux).
     */
    public function filtrerDossiersDepense(Builder $query, User $user): Builder
    {
        if ($this->estGlobal($user)) {
            return $query;
        }
        $chemin = match ($query->getModel()::class) {
            \App\Models\Engagement::class => 'expressionBesoin',
            \App\Models\Liquidation::class => 'engagement.expressionBesoin',
            \App\Models\Ordonnancement::class => 'liquidation.engagement.expressionBesoin',
            \App\Models\Paiement::class => 'ordonnancement.liquidation.engagement.expressionBesoin',
            default => null,
        };
        if ($chemin === null) {
            return $query;
        }
        $ids = $this->structuresAutorisees($user);

        return $query->where(fn (Builder $q) => $q
            ->whereHas($chemin, fn (Builder $b) => $b->whereIn('structure_id', $ids)->orWhereNull('structure_id'))
            ->orWhereDoesntHave($chemin));
    }

    /** Restreint une liste de structures (colonne `id`). */
    public function filtrerReferentielStructures(Builder $query, User $user): Builder
    {
        if ($this->estGlobal($user)) {
            return $query;
        }

        return $query->whereIn('id', $this->structuresAutorisees($user));
    }

    private function etendreAvecAlias(Collection $ids): Collection
    {
        if ($ids->isEmpty()) {
            return $ids;
        }

        $aliasIds = Structure::query()
            ->whereIn('structure_officielle_id', $ids)
            ->pluck('id');

        return $ids->merge($aliasIds)->unique()->values();
    }

    private function collecterDescendants(?Structure $structure, Collection $ids): void
    {
        if (! $structure) {
            return;
        }
        foreach ($structure->enfantsRecursifs as $enfant) {
            $ids->push($enfant->id);
            $this->collecterDescendants($enfant, $ids);
        }
    }
}
