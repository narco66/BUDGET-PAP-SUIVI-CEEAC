<?php

namespace App\Models\Ged;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GedSignature extends Model
{
    protected $table = 'ged_signatures';

    protected $fillable = [
        'document_id', 'version_id', 'signataire_id', 'statut', 'provider', 'niveau',
        'externe_id', 'hash_document', 'empreinte_numerique', 'certificat_interne',
        'date_demande', 'date_signature', 'horodatage', 'horodatage_tsa',
        'token_verification', 'metadonnees_prestataire', 'motif_rejet', 'adresse_ip', 'user_agent',
    ];

    protected function casts(): array
    {
        return [
            'date_demande' => 'datetime',
            'date_signature' => 'datetime',
            'horodatage' => 'datetime',
            'horodatage_tsa' => 'datetime',
            'metadonnees_prestataire' => 'array',
        ];
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(GedDocument::class, 'document_id');
    }

    public function version(): BelongsTo
    {
        return $this->belongsTo(GedVersion::class, 'version_id');
    }

    public function signataire(): BelongsTo
    {
        return $this->belongsTo(User::class, 'signataire_id');
    }
}
