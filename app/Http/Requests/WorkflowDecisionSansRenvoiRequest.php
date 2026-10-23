<?php

namespace App\Http\Requests;

/** Décision workflow sans option « renvoyer » (chaîne dépense, encaissements). */
class WorkflowDecisionSansRenvoiRequest extends WorkflowDecisionRequest
{
    protected function avecRenvoi(): bool
    {
        return false;
    }
}
