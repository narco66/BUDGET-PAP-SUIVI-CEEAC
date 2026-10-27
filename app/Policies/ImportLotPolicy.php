<?php

namespace App\Policies;

use App\Models\ImportLot;
use App\Models\User;
use App\Services\WorkflowService;

/**
 * Habilitations du module Import. Préparation par le créateur du lot ; approbation par l'acteur de l'étape du
 * circuit (titulaire du rôle, distinct de l'initiateur) détenteur de import.approve ; exécution par import.execute.
 * Le droit métier du domaine cible est contrôlé en plus par la stratégie du domaine.
 */
class ImportLotPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('import.view');
    }

    public function view(User $user, ImportLot $lot): bool
    {
        return $user->can('import.view')
            && ((int) $lot->cree_par === $user->id || $user->can('import.history') || $this->agir($user, $lot));
    }

    public function create(User $user): bool
    {
        return $user->can('import.create') && $user->can('import.upload');
    }

    /** Fichier, correspondance, analyse : réservés au préparateur du lot. */
    public function preparer(User $user, ImportLot $lot): bool
    {
        return (int) $lot->cree_par === $user->id
            && $user->can('import.upload') && $user->can('import.map') && $user->can('import.validate');
    }

    public function soumettre(User $user, ImportLot $lot): bool
    {
        return $this->preparer($user, $lot) && $user->can('import.simulate');
    }

    /** Décision d'approbation dans le circuit (Mes Tâches) : rôle de l'étape + import.approve. */
    public function agir(User $user, ImportLot $lot): bool
    {
        $instance = $lot->workflowActif;

        return $instance && $instance->statut === 'en_cours'
            && $user->can('import.approve')
            && app(WorkflowService::class)->peutAgir($instance, $user);
    }

    public function executer(User $user, ImportLot $lot): bool
    {
        return $user->can('import.execute') && $this->view($user, $lot);
    }

    public function annuler(User $user, ImportLot $lot): bool
    {
        return $user->can('import.cancel') && ((int) $lot->cree_par === $user->id || $user->can('import.history'));
    }

    public function telechargerRapport(User $user, ImportLot $lot): bool
    {
        return $user->can('import.download_report') && $this->view($user, $lot);
    }
}
