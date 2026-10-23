<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasPieces;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class AppelFonds extends Model
{
    use Auditable, HasPieces, SoftDeletes;

    protected $table = 'appels_fonds';

    public const STATUTS = [
        'emis' => 'Émis',
        'notifie' => 'Notifié',
        'partiellement_recouvre' => 'Partiellement recouvré',
        'solde' => 'Soldé',
        'annule' => 'Annulé',
    ];

    /** Un appel dont le recouvrement a commencé (ou soldé) ne se supprime plus. */
    public const STATUTS_VERROUILLES = ['solde'];

    protected $fillable = [
        'exercice_id', 'etat_membre_id', 'source_financement_id', 'contribution_id',
        'reference', 'objet', 'montant_appele', 'montant_regle',
        'date_emission', 'date_echeance', 'taux_penalite', 'statut',
        'observations', 'emis_par',
    ];

    protected $casts = [
        'montant_appele' => 'decimal:2',
        'montant_regle' => 'decimal:2',
        'taux_penalite' => 'decimal:2',
        'date_emission' => 'date',
        'date_echeance' => 'date',
    ];

    protected static function booted(): void
    {
        static::deleting(function (AppelFonds $appel) {
            if ($appel->estVerrouille()) {
                throw new \RuntimeException(
                    "Suppression interdite : l'appel de fonds « {$appel->reference} » est soldé ou recouvré."
                );
            }
        });
    }

    public function estVerrouille(): bool
    {
        return in_array($this->statut, self::STATUTS_VERROUILLES, true)
            || (float) $this->montant_regle > 0;
    }

    /** Solde restant à recouvrer (arriéré potentiel). */
    protected function solde(): Attribute
    {
        return Attribute::make(
            get: fn () => max((float) $this->montant_appele - (float) $this->montant_regle, 0),
        );
    }

    /** En retard : échéance dépassée et solde non nul. */
    public function enRetard(): bool
    {
        return $this->date_echeance !== null
            && $this->date_echeance->isPast()
            && $this->solde > 0
            && $this->statut !== 'annule';
    }

    /** Pénalité = taux × arriéré, applicable seulement si l'appel est en retard. */
    protected function penalite(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->enRetard() ? round($this->solde * (float) $this->taux_penalite / 100, 2) : 0.0,
        );
    }

    protected function tauxPaiement(): Attribute
    {
        return Attribute::make(
            get: fn () => (float) $this->montant_appele > 0
                ? round((float) $this->montant_regle / (float) $this->montant_appele * 100, 1)
                : 0.0,
        );
    }

    public function exercice(): BelongsTo
    {
        return $this->belongsTo(Exercice::class);
    }

    public function etatMembre(): BelongsTo
    {
        return $this->belongsTo(EtatMembre::class);
    }

    public function sourceFinancement(): BelongsTo
    {
        return $this->belongsTo(SourceFinancement::class);
    }

    public function contribution(): BelongsTo
    {
        return $this->belongsTo(Contribution::class);
    }

    public function emisPar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'emis_par');
    }

    public function relances(): HasMany
    {
        return $this->hasMany(Relance::class, 'appel_fonds_id')->latest('date_relance');
    }

    public function plansApurement(): HasMany
    {
        return $this->hasMany(PlanApurement::class, 'appel_fonds_id')->latest();
    }

    public function planApurementActif(): ?PlanApurement
    {
        return $this->plansApurement()->where('statut', 'actif')->first();
    }
}
