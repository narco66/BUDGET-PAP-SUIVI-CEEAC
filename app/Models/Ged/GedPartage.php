<?php

namespace App\Models\Ged;

use App\Models\Structure;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Permission\Models\Role;

class GedPartage extends Model
{
    protected $table = 'ged_partages';

    protected $fillable = [
        'document_id', 'partage_par', 'partage_avec', 'structure_id', 'role_id',
        'peut_telecharger', 'peut_previsualiser', 'expire_le', 'commentaire',
    ];

    protected function casts(): array
    {
        return [
            'peut_telecharger' => 'boolean',
            'peut_previsualiser' => 'boolean',
            'expire_le' => 'datetime',
        ];
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(GedDocument::class, 'document_id');
    }

    public function partagePar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'partage_par');
    }

    public function partageAvec(): BelongsTo
    {
        return $this->belongsTo(User::class, 'partage_avec');
    }

    public function structure(): BelongsTo
    {
        return $this->belongsTo(Structure::class);
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }
}
