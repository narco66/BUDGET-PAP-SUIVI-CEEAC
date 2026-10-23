<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Mesure extends Model
{
    use Auditable;

    protected $fillable = [
        'indicateur_id', 'saisi_par', 'verifie_par', 'verifie_le', 'valide_par', 'valide_le',
        'periode', 'valeur_realisee', 'taux_atteinte', 'feu', 'commentaire', 'statut', 'version',
    ];

    protected $casts = [
        'valeur_realisee' => 'decimal:4',
        'taux_atteinte' => 'decimal:2',
        'verifie_le' => 'datetime',
        'valide_le' => 'datetime',
        'version' => 'integer',
    ];

    public function indicateur(): BelongsTo
    {
        return $this->belongsTo(Indicateur::class);
    }

    public function auteur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'saisi_par');
    }

    public function verificateur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verifie_par');
    }

    public function validateur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'valide_par');
    }

    public function historiques(): HasMany
    {
        return $this->hasMany(HistoriqueMesure::class)->orderByDesc('created_at');
    }
}
