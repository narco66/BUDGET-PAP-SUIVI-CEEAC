<?php

namespace App\Policies;

use App\Models\Paiement;
use App\Models\User;

class PaiementPolicy extends EtapeDepensePolicy
{
    protected function permissionPrefix(): string
    {
        return 'paiement';
    }

    /** Constat de rejet d’instrument (après visa), distinct du rejet de circuit. */
    public function contrepasser(User $user, Paiement $paiement): bool
    {
        return $user->can('paiement.valider') && $paiement->statut === 'valide';
    }
}
