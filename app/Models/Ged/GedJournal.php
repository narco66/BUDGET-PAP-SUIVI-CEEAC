<?php

namespace App\Models\Ged;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GedJournal extends Model
{
    protected $table = 'ged_journaux';

    protected $fillable = [
        'document_id', 'utilisateur_id', 'action', 'adresse_ip', 'user_agent',
        'ancienne_valeur', 'nouvelle_valeur', 'commentaire', 'module_source',
    ];

    protected function casts(): array
    {
        return [
            'ancienne_valeur' => 'array',
            'nouvelle_valeur' => 'array',
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
}
