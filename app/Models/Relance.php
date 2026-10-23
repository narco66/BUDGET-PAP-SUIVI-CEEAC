<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Relance extends Model
{
    use Auditable;

    protected $table = 'relances_recouvrements';

    public const CANAUX = [
        'courrier' => 'Courrier officiel',
        'email' => 'Courriel',
        'note_verbale' => 'Note verbale',
        'telephone' => 'Téléphone',
        'mise_en_demeure' => 'Mise en demeure',
    ];

    public const NIVEAUX = [
        1 => '1ʳᵉ relance',
        2 => '2ᵉ relance',
        3 => 'Relance finale / mise en demeure',
    ];

    protected $fillable = [
        'appel_fonds_id', 'niveau', 'date_relance', 'canal',
        'destinataire', 'objet', 'montant_du', 'automatique', 'observations', 'emise_par',
        'statut_envoi', 'email_log_id', 'notification_log_id',
    ];

    protected $casts = [
        'niveau' => 'integer',
        'date_relance' => 'date',
        'montant_du' => 'decimal:2',
        'automatique' => 'boolean',
    ];

    public function libelleNiveau(): string
    {
        return self::NIVEAUX[$this->niveau] ?? ($this->niveau.'ᵉ relance');
    }

    public function appel(): BelongsTo
    {
        return $this->belongsTo(AppelFonds::class, 'appel_fonds_id');
    }

    public function emisePar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'emise_par');
    }
}
