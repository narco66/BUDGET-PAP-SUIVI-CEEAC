<?php

namespace App\Models\Ged;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GedNumerisation extends Model
{
    protected $table = 'ged_numerisations';

    protected $fillable = [
        'document_id', 'numerise_par', 'source', 'appareil', 'dpi', 'ocr_applique', 'notes',
    ];

    protected function casts(): array
    {
        return ['ocr_applique' => 'boolean'];
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(GedDocument::class, 'document_id');
    }

    public function numerisePar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'numerise_par');
    }
}
