<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class PapRisque extends Model
{
    use SoftDeletes, Auditable;

    protected $fillable = [
        'pap_id', 'activite_id', 'code', 'libelle', 'description',
        'probabilite', 'impact', 'statut', 'mitigation', 'responsable_user_id',
    ];

    public function pap(): BelongsTo
    {
        return $this->belongsTo(Pap::class);
    }

    public function activite(): BelongsTo
    {
        return $this->belongsTo(Activite::class);
    }

    public function responsable(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsable_user_id');
    }

    public function mesuresCorrectives(): HasMany
    {
        return $this->hasMany(PapMesureCorrective::class);
    }

    public function niveauCriticite(): string
    {
        $score = match ($this->probabilite) {
            'elevee' => 3,
            'moyenne' => 2,
            default => 1,
        } * match ($this->impact) {
            'critique' => 4,
            'eleve' => 3,
            'moyen' => 2,
            default => 1,
        };

        return match (true) {
            $score >= 9 => 'critique',
            $score >= 6 => 'eleve',
            $score >= 3 => 'moyen',
            default => 'faible',
        };
    }
}
