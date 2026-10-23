<?php

namespace App\Models\Ged;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GedArchive extends Model
{
    protected $table = 'ged_archives';

    protected $fillable = [
        'document_id', 'motif', 'commentaire', 'type_archivage', 'date_archivage',
        'archived_by', 'est_restaure', 'date_restauration', 'restored_by', 'motif_restauration',
    ];

    protected function casts(): array
    {
        return [
            'date_archivage' => 'datetime',
            'date_restauration' => 'datetime',
            'est_restaure' => 'boolean',
        ];
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(GedDocument::class, 'document_id');
    }

    public function archiveur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'archived_by');
    }
}
