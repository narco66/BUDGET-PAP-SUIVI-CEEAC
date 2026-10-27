<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Exercice extends Model
{
    use HasFactory;

    public const STATUTS = [
        'brouillon' => 'Brouillon',
        'ouvert' => 'Ouvert',
        'clos' => 'Clôturé',
    ];

    protected $fillable = [
        'code', 'annee', 'libelle', 'statut', 'est_actif', 'date_debut', 'date_fin',
        'date_cloture', 'cloture_par', 'devise', 'taux_change_usd',
    ];

    protected $casts = [
        'date_debut' => 'date',
        'date_fin' => 'date',
        'date_cloture' => 'datetime',
        'est_actif' => 'boolean',
        'taux_change_usd' => 'decimal:6',
    ];

    /** Exercice de référence de l'interface : l'exercice actif, à défaut le plus récent (mémorisé pour la requête). */
    public static function courant(): ?self
    {
        return once(fn () => static::query()->where('est_actif', true)->first() ?? static::query()->orderByDesc('annee')->first());
    }

    public function scopeActif($q)
    {
        return $q->where('est_actif', true);
    }

    public function scopeOuvert($q)
    {
        return $q->where('statut', 'ouvert');
    }

    public function estClos(): bool
    {
        return $this->statut === 'clos';
    }

    public function estModifiable(): bool
    {
        return $this->statut !== 'clos';
    }

    public function cloturePar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cloture_par');
    }

    public function pap(): HasOne
    {
        return $this->hasOne(Pap::class);
    }

    public function budgetInstitutionnel(): HasOne
    {
        return $this->hasOne(Budget::class);
    }

    public function piliers(): HasMany
    {
        return $this->hasMany(Pilier::class)->orderBy('ordre');
    }

    public function lignesBudgetaires(): HasMany
    {
        return $this->hasMany(LigneBudgetaire::class);
    }

    public function contributions(): HasMany
    {
        return $this->hasMany(Contribution::class);
    }

    public function previsionsRecettes(): HasMany
    {
        return $this->hasMany(PrevisionRecette::class);
    }

    public function recettes(): HasMany
    {
        return $this->hasMany(Recette::class);
    }
}
