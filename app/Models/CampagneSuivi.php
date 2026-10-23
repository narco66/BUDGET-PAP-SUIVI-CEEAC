<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasGedDocuments;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class CampagneSuivi extends Model
{
    use SoftDeletes, Auditable, HasGedDocuments;

    protected $table = 'campagnes_suivi';

    protected $fillable = ['exercice_id', 'libelle', 'type', 'periode', 'date_debut', 'date_fin', 'statut'];

    protected $casts = ['date_debut' => 'date', 'date_fin' => 'date'];

    public function exercice(): BelongsTo
    {
        return $this->belongsTo(Exercice::class);
    }

    public function collectes(): HasMany
    {
        return $this->hasMany(Collecte::class, 'campagne_id');
    }

    public function realisationsProduits(): HasMany
    {
        return $this->hasMany(RealisationProduit::class, 'campagne_id');
    }

    public function realisationsSousProduits(): HasMany
    {
        return $this->hasMany(RealisationSousProduit::class, 'campagne_id');
    }

    public function alertes(): HasMany
    {
        return $this->hasMany(AlertePerformance::class, 'campagne_id');
    }

    public function estOuverte(): bool
    {
        return $this->statut === 'ouverte';
    }
}
