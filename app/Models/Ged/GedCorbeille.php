<?php

namespace App\Models\Ged;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GedCorbeille extends Model
{
    protected $table = 'ged_corbeille';

    protected $fillable = [
        'document_id', 'supprime_par', 'motif', 'date_suppression',
        'date_expiration_corbeille', 'est_restaure',
    ];

    protected function casts(): array
    {
        return [
            'date_suppression' => 'datetime',
            'date_expiration_corbeille' => 'datetime',
            'est_restaure' => 'boolean',
        ];
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(GedDocument::class, 'document_id');
    }

    public function supprimePar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'supprime_par');
    }
}
