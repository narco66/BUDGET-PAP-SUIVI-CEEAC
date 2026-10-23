<?php

namespace App\Models\Concerns;

use App\Models\RbmHistorique;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Colonnes communes du référentiel RBM/GAR (statut, traçabilité utilisateur).
 */
trait HasRbmReferentiel
{
    public static function bootHasRbmReferentiel(): void
    {
        static::creating(function ($model) {
            if (auth()->check() && ! $model->created_by) {
                $model->created_by = auth()->id();
            }
            $colStatut = $model->colonneStatutReferentiel();
            if (! $model->{$colStatut}) {
                $model->{$colStatut} = 'actif';
            }
        });

        static::updating(function ($model) {
            if (auth()->check()) {
                $model->updated_by = auth()->id();
            }
        });
    }

    /** Colonne de statut référentiel (taches utilise statut pour le workflow). */
    public function colonneStatutReferentiel(): string
    {
        return $this->getTable() === 'taches' ? 'statut_referentiel' : 'statut';
    }

    public function createur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function modificateur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function historiques(): MorphMany
    {
        return $this->morphMany(RbmHistorique::class, 'historisable')->latest();
    }

    public function scopeActif($query)
    {
        $col = $this->colonneStatutReferentiel();

        return $query->where($col, 'actif');
    }

    public function estArchive(): bool
    {
        return $this->{$this->colonneStatutReferentiel()} === 'archive';
    }
}
