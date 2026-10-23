<?php



namespace App\Policies;



use App\Models\User;

use App\Rbm\Niveaux;

use App\Support\RbmAuthorization;

use Illuminate\Database\Eloquent\Model;



/**

 * Policy générique pour les nœuds de la chaîne RBM/GAR.

 * Alignée sur RbmAuthorization (permissions globales + granulaires par niveau).

 */

class RbmNodePolicy

{

    public function viewAny(User $user): bool

    {

        return $user->can('rbm.voir');

    }



    public function view(User $user, Model $noeud): bool

    {

        return $this->autorise($user, $noeud, 'voir') || $user->can('rbm.voir');

    }



    public function create(User $user): bool

    {

        return $user->can('rbm.creer');

    }



    public function update(User $user, Model $noeud): bool

    {

        return $this->autorise($user, $noeud, 'modifier');

    }



    public function delete(User $user, Model $noeud): bool

    {

        return $this->autorise($user, $noeud, 'supprimer');

    }



    public function archive(User $user, Model $noeud): bool

    {

        return $this->autorise($user, $noeud, 'archiver');

    }



    public function restore(User $user, Model $noeud): bool

    {

        return $this->autorise($user, $noeud, 'restaurer');

    }



    public function export(User $user): bool

    {

        return $user->can('rbm.exporter');

    }



    public function import(User $user): bool

    {

        return $user->can('rbm.importer');

    }



    private function autorise(User $user, Model $noeud, string $action): bool

    {

        $niveau = $this->niveauPour($noeud);



        return $niveau && RbmAuthorization::peut($user, $niveau, $action);

    }



    private function niveauPour(Model $noeud): ?string

    {

        foreach (Niveaux::tout() as $niveau => $config) {

            if ($noeud instanceof $config['model']) {

                return $niveau;

            }

        }



        return null;

    }

}

