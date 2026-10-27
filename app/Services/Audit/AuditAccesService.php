<?php

namespace App\Services\Audit;

use App\Models\JournalAudit;
use App\Models\Structure;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Cloisonnement de la consultation des journaux (§ 8, JNL-012), appliqué côté serveur :
 * - étendu (audit.events.view_extended) : tous les événements ;
 * - responsable d'une structure (organigramme) : ses événements et ceux des agents de sa structure et
 *   des structures rattachées, hors journal de sécurité ;
 * - autres titulaires de audit.voir : leurs propres événements ;
 * - journal de sécurité d'autrui : audit.security.view.
 * Un périmètre de consultation n'accorde aucun droit de décision.
 */
class AuditAccesService
{
    /** @var array<int, Collection<int, int>> */
    private array $unites = [];

    public function peutAcceder(User $user): bool
    {
        return $user->can('audit.events.view_own');
    }

    public function etendu(User $user): bool
    {
        return $user->can('audit.events.view_extended');
    }

    public function securite(User $user): bool
    {
        return $user->can('audit.security.view');
    }

    /** Structures dont l'utilisateur est responsable, et leurs descendantes. */
    public function unites(User $user): Collection
    {
        if (isset($this->unites[$user->id])) {
            return $this->unites[$user->id];
        }
        $racines = Structure::query()->where('responsable_user_id', $user->id)->pluck('id');
        $ids = collect($racines);
        $frontiere = $racines;
        while ($frontiere->isNotEmpty()) {
            $frontiere = Structure::query()->whereIn('parent_id', $frontiere)->whereNotIn('id', $ids)->pluck('id');
            $ids = $ids->merge($frontiere);
        }

        return $this->unites[$user->id] = $ids->unique()->values();
    }

    public function perimetre(User $user): string
    {
        return match (true) {
            $this->etendu($user) => 'etendu',
            $this->unites($user)->isNotEmpty() => 'unite',
            default => 'propre',
        };
    }

    /** Restreint une requête sur journaux_audit au périmètre de l'utilisateur. */
    public function filtrer(Builder $q, User $user): Builder
    {
        if ($this->etendu($user)) {
            return $q;
        }
        $unites = $this->unites($user);
        $securite = $this->securite($user);

        return $q->where(function (Builder $b) use ($user, $unites, $securite) {
            $b->where('user_id', $user->id);
            if ($unites->isNotEmpty() || $securite) {
                $b->orWhere(function (Builder $c) use ($unites, $securite) {
                    if ($unites->isNotEmpty()) {
                        $c->whereIn('structure_id', $unites);
                    }
                    if (! $securite) {
                        $c->where(fn (Builder $d) => $d->whereNull('categorie')->orWhere('categorie', '!=', 'securite'));
                    } elseif ($unites->isEmpty()) {
                        $c->where('categorie', 'securite');
                    }
                });
            }
        });
    }

    public function peutConsulter(JournalAudit $j, User $user): bool
    {
        if ($this->etendu($user) || (int) $j->user_id === $user->id) {
            return true;
        }
        if ($j->categorie === 'securite') {
            return $this->securite($user);
        }

        return $j->structure_id !== null && $this->unites($user)->contains((int) $j->structure_id);
    }
}
