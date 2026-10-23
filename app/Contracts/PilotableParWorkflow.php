<?php

namespace App\Contracts;

/** Implémenté par les objets métier dont l'état suit un circuit de workflow. */
interface PilotableParWorkflow
{
    /** Le circuit vient d'être démarré (objet soumis). */
    public function auWorkflowDemarre(): void;

    /** Une étape intermédiaire a été validée (l'objet avance). */
    public function auEtapeValidee(int $ordre): void;

    /** Toutes les étapes sont validées (objet approuvé / publié). */
    public function auWorkflowValide(): void;

    /** Le circuit a été rejeté. */
    public function auWorkflowRejete(): void;
}
