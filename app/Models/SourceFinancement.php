<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SourceFinancement extends Model
{
    use HasFactory;

    protected $table = 'sources_financement';

    public const TYPES = [
        'interne_ceeac' => 'Interne CEEAC',
        'externe_ptf' => 'Externe PTF',
    ];

    public const CLASSIFICATIONS = [
        'contribution_statutaire' => 'Contributions statutaires',
        'contribution_volontaire' => 'Contributions volontaires',
        'fonds_propres' => 'Fonds propres',
        'fonds_communautaires' => 'Fonds communautaires',
        'subvention' => 'Subventions',
        'cooperation' => 'Coopérations',
        'projet_finance' => 'Projets financés',
        'fonds_special' => 'Fonds spéciaux',
    ];

    protected $fillable = [
        'partenaire_id', 'code', 'libelle', 'type', 'classification', 'bailleur', 'description', 'actif',
    ];

    protected $casts = ['actif' => 'boolean'];

    public function partenaire(): BelongsTo
    {
        return $this->belongsTo(PartenaireFinancier::class, 'partenaire_id');
    }

    public function lignesBudgetaires(): HasMany
    {
        return $this->hasMany(LigneBudgetaire::class);
    }

    public function conventions(): HasMany
    {
        return $this->hasMany(ConventionFinancement::class);
    }

    public function libelleComplet(): string
    {
        if (! $this->partenaire) {
            return "{$this->code} · {$this->libelle}";
        }

        $ptf = $this->partenaire->sigle ?: $this->partenaire->libelle;

        return "{$this->code} · {$this->libelle} ({$ptf})";
    }
}
