<?php

namespace App\Policies;

class OrdonnancementPolicy extends EtapeDepensePolicy
{
    protected function permissionPrefix(): string
    {
        return 'ordonnancement';
    }
}
