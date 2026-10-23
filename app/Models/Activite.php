<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasGedDocuments;
use App\Models\Concerns\HasIndicateurs;
use App\Models\Concerns\HasRbmReferentiel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Activite extends Model
{
    use HasFactory, SoftDeletes, HasIndicateurs, Auditable, HasRbmReferentiel, HasGedDocuments;

    protected $fillable = [
        'sous_produit_id', 'produit_id', 'structure_id', 'responsable_user_id', 'ligne_budgetaire_id', 'projet_id',
        'code', 'intitule', 'description',
        'montant_total', 'montant_ceeac', 'montant_ptf',
        'trim_1', 'trim_2', 'trim_3', 'trim_4',
        'taux_realisation_physique', 'taux_realisation_financier', 'cause_ecart', 'mesure_corrective', 'ordre',
        'statut', 'created_by', 'updated_by',
    ];

    protected $casts = [
        'montant_total' => 'decimal:2',
        'montant_ceeac' => 'decimal:2',
        'montant_ptf' => 'decimal:2',
        'trim_1' => 'boolean', 'trim_2' => 'boolean', 'trim_3' => 'boolean', 'trim_4' => 'boolean',
    ];

    public function sousProduit(): BelongsTo
    {
        return $this->belongsTo(SousProduit::class);
    }

    public function produit(): BelongsTo
    {
        return $this->belongsTo(Produit::class);
    }

    public function structure(): BelongsTo
    {
        return $this->belongsTo(Structure::class);
    }

    public function responsable(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsable_user_id');
    }

    public function ligneBudgetaire(): BelongsTo
    {
        return $this->belongsTo(LigneBudgetaire::class);
    }

    public function projetStrategique(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Strategie\ProjetStrategique::class, 'projet_id');
    }

    public function taches(): HasMany
    {
        return $this->hasMany(Tache::class)->orderBy('ordre');
    }

    public function collectes(): HasMany
    {
        return $this->hasMany(Collecte::class);
    }

    protected static function booted(): void
    {
        static::saving(function (Activite $activite) {
            if (! $activite->sous_produit_id) {
                throw new \RuntimeException('Une activité doit être rattachée à un sous-produit.');
            }

            if (! $activite->produit_id && $activite->sous_produit_id) {
                $activite->produit_id = SousProduit::find($activite->sous_produit_id)?->produit_id;
            }
        });
    }
}
