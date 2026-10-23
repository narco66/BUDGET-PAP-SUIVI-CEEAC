<?php

namespace App\Contracts;

/** Objet métier rattaché à une structure pour la résolution des validateurs institutionnels. */
interface ConcernantStructureWorkflow
{
    public function structureIdWorkflow(): ?int;
}
