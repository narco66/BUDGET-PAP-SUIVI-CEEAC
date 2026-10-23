<?php

namespace App\Models\Ged;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GedVersion extends Model
{
    protected $table = 'ged_versions';

    protected $fillable = [
        'document_id', 'numero', 'chemin_fichier', 'nom_original', 'extension',
        'mime_type', 'taille', 'hash_sha256', 'statut', 'commentaire', 'created_by',
    ];

    public function document(): BelongsTo
    {
        return $this->belongsTo(GedDocument::class, 'document_id');
    }

    public function auteur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
