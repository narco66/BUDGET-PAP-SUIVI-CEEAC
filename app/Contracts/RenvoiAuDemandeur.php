<?php

namespace App\Contracts;

/**
 * Dossier dont le « retour pour correction » revient au demandeur (et non au valideur précédent) :
 * le circuit reste positionné sur l'étape qui a renvoyé et reprend à ce niveau à la nouvelle soumission.
 */
interface RenvoiAuDemandeur
{
    /** Le dossier attend-il la correction du demandeur ? */
    public function enCorrection(): bool;

    /** Le demandeur a resoumis le dossier corrigé : le circuit reprend. */
    public function auCircuitRepris(): void;
}
