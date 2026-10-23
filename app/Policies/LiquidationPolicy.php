<?php

namespace App\Policies;

class LiquidationPolicy extends EtapeDepensePolicy
{
    protected function permissionPrefix(): string
    {
        return 'liquidation';
    }
}
