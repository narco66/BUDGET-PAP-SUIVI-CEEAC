<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/** Immobilisation corporelle / incorporelle — inventaire patrimonial (P2-1). */
class Immobilisation extends Model
{
    use Auditable, SoftDeletes;

    protected $fillable = [
        'numero', 'exercice_id', 'structure_id', 'engagement_id',
        'categorie', 'designation', 'marque', 'numero_serie',
        'localisation', 'affectation', 'date_acquisition',
        'valeur_acquisition', 'duree_amortissement_ans', 'compte_syscohada',
        'statut', 'observations',
    ];

    protected $casts = [
        'date_acquisition' => 'date',
        'valeur_acquisition' => 'decimal:2',
        'duree_amortissement_ans' => 'integer',
    ];

    public const CATEGORIES = [
        'informatique' => 'Informatique',
        'vehicule' => 'Véhicules',
        'batiment' => 'Bâtiments',
        'mobilier' => 'Mobilier & équipements',
    ];

    public const STATUTS = [
        'en_service' => 'En service',
        'en_maintenance' => 'En maintenance',
        'reforme' => 'Réformé',
        'cede' => 'Cédé',
    ];

    public function exercice(): BelongsTo
    {
        return $this->belongsTo(Exercice::class);
    }

    public function structure(): BelongsTo
    {
        return $this->belongsTo(Structure::class);
    }

    public function engagement(): BelongsTo
    {
        return $this->belongsTo(Engagement::class);
    }

    /** Amortissement linéaire cumulé à la date du jour. */
    public function amortissementCumule(?\DateTimeInterface $aLaDate = null): float
    {
        $valeur = (float) $this->valeur_acquisition;
        $duree = max(1, (int) $this->duree_amortissement_ans);
        if ($valeur <= 0 || ! $this->date_acquisition) {
            return 0.0;
        }

        $fin = $aLaDate ?? now();
        $annees = max(0, $this->date_acquisition->diff($fin)->y + ($this->date_acquisition->diff($fin)->m / 12));

        return min($valeur, round($valeur / $duree * $annees, 2));
    }

    public function valeurNette(?\DateTimeInterface $aLaDate = null): float
    {
        return max(0, round((float) $this->valeur_acquisition - $this->amortissementCumule($aLaDate), 2));
    }
}
