<?php

namespace App\Policies;

class PaiementPolicy extends EtapeDepensePolicy
{
    protected function permissionPrefix(): string
    {
        return 'paiement';
    }
}
