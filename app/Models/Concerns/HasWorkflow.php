<?php

namespace App\Models\Concerns;

use App\Models\WorkflowInstance;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;

/** Rend un modèle pilotable par le moteur de workflow. */
trait HasWorkflow
{
    public function workflowInstances(): MorphMany
    {
        return $this->morphMany(WorkflowInstance::class, 'objet');
    }

    public function workflowActif(): MorphOne
    {
        return $this->morphOne(WorkflowInstance::class, 'objet')->latestOfMany();
    }
}
