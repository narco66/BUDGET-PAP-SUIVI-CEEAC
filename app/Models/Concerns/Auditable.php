<?php

namespace App\Models\Concerns;

use App\Models\JournalAudit;
use App\Support\AuditLogger;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Trace automatiquement created / updated / deleted dans la piste d'audit.
 * À ajouter sur tout modèle dont les écritures sont sensibles.
 */
trait Auditable
{
    public static function bootAuditable(): void
    {
        static::created(fn ($model) => AuditLogger::enregistrer('created', $model, null, $model->getAttributes()));

        static::updated(function ($model) {
            if (! empty($model->getChanges())) {
                AuditLogger::enregistrer(
                    'updated',
                    $model,
                    array_intersect_key($model->getOriginal(), $model->getChanges()),
                    $model->getChanges(),
                );
            }
        });

        static::deleted(fn ($model) => AuditLogger::enregistrer('deleted', $model, $model->getOriginal(), null));
    }

    public function journaux(): MorphMany
    {
        return $this->morphMany(JournalAudit::class, 'auditable');
    }
}
