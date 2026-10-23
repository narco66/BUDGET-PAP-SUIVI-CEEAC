<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PartenaireFinancier extends Model
{
    protected $table = 'partenaires_financiers';

    public const TYPES = [
        'multilateral' => 'Multilatéral',
        'bilateral' => 'Bilatéral',
        'onu' => 'Système ONU',
        'technique' => 'Partenaire technique',
        'autre' => 'Autre',
    ];

    protected $fillable = ['code', 'libelle', 'sigle', 'type', 'pays', 'description', 'actif'];

    protected $casts = ['actif' => 'boolean'];

    public function sources(): HasMany
    {
        return $this->hasMany(SourceFinancement::class, 'partenaire_id');
    }

    public function conventions(): HasMany
    {
        return $this->hasMany(ConventionFinancement::class, 'partenaire_id');
    }
}
