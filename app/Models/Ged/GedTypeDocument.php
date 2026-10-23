<?php

namespace App\Models\Ged;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class GedTypeDocument extends Model
{
    use SoftDeletes;

    protected $table = 'ged_types_documents';

    protected $fillable = [
        'code', 'libelle', 'description', 'categorie_id', 'extensions_autorisees',
        'taille_max_mo', 'signature_requise', 'actif', 'created_by', 'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'extensions_autorisees' => 'array',
            'signature_requise' => 'boolean',
            'actif' => 'boolean',
        ];
    }

    public function categorie(): BelongsTo
    {
        return $this->belongsTo(GedCategorie::class, 'categorie_id');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(GedDocument::class, 'type_document_id');
    }

    public function reglesConservation(): HasMany
    {
        return $this->hasMany(GedConservationRule::class, 'type_document_id');
    }
}
