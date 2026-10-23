<?php

namespace App\Models\Ged;

use App\Models\Structure;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Permission\Models\Role;

class GedAcces extends Model
{
    protected $table = 'ged_acces';

    protected $fillable = [
        'document_id', 'utilisateur_id', 'role_id', 'structure_id',
        'peut_voir', 'peut_telecharger', 'peut_modifier', 'peut_supprimer',
        'peut_archiver', 'peut_signer',
    ];

    protected function casts(): array
    {
        return [
            'peut_voir' => 'boolean',
            'peut_telecharger' => 'boolean',
            'peut_modifier' => 'boolean',
            'peut_supprimer' => 'boolean',
            'peut_archiver' => 'boolean',
            'peut_signer' => 'boolean',
        ];
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(GedDocument::class, 'document_id');
    }

    public function utilisateur(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function structure(): BelongsTo
    {
        return $this->belongsTo(Structure::class);
    }
}
