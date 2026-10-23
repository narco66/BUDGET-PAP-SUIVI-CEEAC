<?php

namespace App\Models\Ged;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GedValidation extends Model
{
    protected $table = 'ged_validations';

    protected $fillable = [
        'document_id', 'workflow_etape_id', 'validateur_id', 'statut',
        'commentaire', 'motif_rejet', 'date_action', 'delai_jours',
    ];

    protected function casts(): array
    {
        return ['date_action' => 'datetime'];
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(GedDocument::class, 'document_id');
    }

    public function etape(): BelongsTo
    {
        return $this->belongsTo(GedWorkflowEtape::class, 'workflow_etape_id');
    }

    public function validateur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'validateur_id');
    }
}
