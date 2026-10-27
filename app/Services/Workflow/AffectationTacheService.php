<?php

namespace App\Services\Workflow;

use App\Models\Delegation;
use App\Models\Interim;
use App\Models\Structure;
use App\Models\User;
use App\Services\Institution\WorkflowInstitutionResolver;
use Illuminate\Support\Collection;

/**
 * Règle unique d'affectation d'une action (étape de circuit ou tâche de préparation) : qui doit agir.
 * Partagée par les notifications, « Mes Tâches » et le moteur de workflow, pour que la personne notifiée
 * soit exactement celle qui voit la tâche et peut la traiter.
 *
 * 1. Rôle rattaché à un niveau organique (chef de service, directeur, commissaire, SG, Président) :
 *    le responsable de la structure validatrice et ses suppléants ; à défaut, les porteurs du rôle
 *    affectés à cette structure ou à l'une de ses sous-structures.
 * 2. Rôle transversal (contrôleur financier, gestionnaire budgétaire, comptables…) : ses porteurs.
 * 3. Aucun titulaire organique trouvé : tous les porteurs du rôle, l'affectation étant signalée
 *    « par défaut » (anomalie de paramétrage visible en supervision) — jamais une attribution silencieuse.
 */
class AffectationTacheService
{
    /** @var array<string, array{users: Collection<int, User>, defaut: bool}> */
    private array $cache = [];

    public function __construct(private readonly WorkflowInstitutionResolver $institution) {}

    /** @return array{users: Collection<int, User>, defaut: bool} */
    public function titulaires(string $role, ?int $structureOrigineId): array
    {
        $cle = $role.'|'.($structureOrigineId ?? 0);
        if (isset($this->cache[$cle])) {
            return $this->cache[$cle];
        }

        $porteurs = fn () => User::query()->where('actif', true)->role($role)->get();
        if (! $this->estRoleOrganique($role)) {
            return $this->cache[$cle] = ['users' => $porteurs(), 'defaut' => false];
        }

        $cible = $this->institution->structureValidateur($structureOrigineId, $role);
        if ($cible) {
            $responsable = $cible->responsable_user_id ? User::query()->where('actif', true)->find($cible->responsable_user_id) : null;
            if ($responsable && $responsable->hasRole($role)) {
                $suppleants = Delegation::query()->enCours()->where('structure_id', $cible->id)->where('delegue_par', $responsable->id)->pluck('delegue_a')
                    ->merge(Interim::query()->enCours()->where('structure_id', $cible->id)->where('titulaire_user_id', $responsable->id)->pluck('interim_user_id'));
                $users = User::query()->where('actif', true)->whereIn('id', $suppleants->push($responsable->id)->unique())->get();

                return $this->cache[$cle] = ['users' => $users, 'defaut' => false];
            }

            $affectes = User::query()->where('actif', true)->role($role)->whereIn('structure_id', $this->sousArbre($cible))->get();
            if ($affectes->isNotEmpty()) {
                return $this->cache[$cle] = ['users' => $affectes, 'defaut' => false];
            }
        }

        return $this->cache[$cle] = ['users' => $porteurs(), 'defaut' => true];
    }

    /** L'utilisateur fait-il partie des titulaires de l'action (ou supplée-t-il le responsable de la structure) ? */
    public function estTitulaire(User $user, string $role, ?int $structureOrigineId): bool
    {
        if ($this->titulaires($role, $structureOrigineId)['users']->contains('id', $user->id)) {
            return true;
        }

        return $structureOrigineId !== null && $this->institution->peutAgirSurEtape($user, $structureOrigineId, $role);
    }

    public function estRoleOrganique(string $role): bool
    {
        return in_array($role, WorkflowInstitutionResolver::rolesOrganiques(), true);
    }

    /** @return list<int> la structure et toutes ses sous-structures */
    private function sousArbre(Structure $racine): array
    {
        $ids = [$racine->id];
        $frontiere = [$racine->id];
        while ($frontiere) {
            $frontiere = Structure::query()->whereIn('parent_id', $frontiere)->pluck('id')->all();
            $ids = array_merge($ids, $frontiere);
        }

        return $ids;
    }
}
