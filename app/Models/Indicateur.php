<?php

namespace App\Models;

use App\Models\Concerns\HasGedDocuments;
use App\Models\Concerns\HasRbmReferentiel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Indicateur extends Model
{
    use HasFactory, HasRbmReferentiel, SoftDeletes, HasGedDocuments;

    protected $fillable = [
        'rbm_id', 'rbm_type', 'code', 'libelle', 'type', 'type_indicateur_id', 'unite', 'unite_mesure_id',
        'methode_calcul', 'source_verification', 'periodicite',
        'valeur_reference', 'valeur_cible', 'seuil_alerte',
        'statut', 'created_by', 'updated_by',
    ];

    protected $casts = [
        'valeur_reference' => 'decimal:4',
        'valeur_cible' => 'decimal:4',
        'seuil_alerte' => 'decimal:4',
    ];

    public function rbm(): MorphTo
    {
        return $this->morphTo();
    }

    public function typeIndicateur(): BelongsTo
    {
        return $this->belongsTo(TypeIndicateur::class, 'type_indicateur_id');
    }

    public function uniteMesure(): BelongsTo
    {
        return $this->belongsTo(UniteMesure::class, 'unite_mesure_id');
    }

    public function sourcesVerification(): BelongsToMany
    {
        return $this->belongsToMany(SourceVerification::class, 'indicateur_source_verification')
            ->withTimestamps();
    }

    public function mesures(): HasMany
    {
        return $this->hasMany(Mesure::class);
    }

    protected static function booted(): void
    {
        static::saving(function (Indicateur $indicateur) {
            app(\App\Services\Rbm\IndicateurIntegriteService::class)->synchroniserModele($indicateur);
        });
    }

    /** Niveau RBM (pilier, axe, …) rattaché à cet indicateur. */
    public function niveauRbm(): ?string
    {
        foreach (\App\Rbm\Niveaux::tout() as $niveau => $cfg) {
            if ($cfg['model'] === $this->rbm_type) {
                return $niveau;
            }
        }

        return null;
    }

    /** URL de la page mesures / indicateurs du nœud RBM parent. */
    public function urlMesures(?int $exerciceId = null): ?string
    {
        $niveau = $this->niveauRbm();
        if (! $niveau) {
            return null;
        }

        $url = route('rbm.indicateurs.index', [$niveau, $this->rbm_id]);

        return $exerciceId ? $url.'?exercice='.$exerciceId : $url;
    }
}
