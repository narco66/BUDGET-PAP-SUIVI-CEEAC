<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ConventionFinancement extends Model
{
    protected $table = 'conventions_financement';

    public const STATUTS = [
        'brouillon' => 'Brouillon',
        'actif' => 'Actif',
        'suspendu' => 'Suspendu',
        'clos' => 'Clôturé',
    ];

    protected $fillable = [
        'partenaire_id', 'source_financement_id', 'exercice_id', 'code', 'libelle',
        'reference_accord', 'date_signature', 'date_debut', 'date_fin',
        'montant_accord', 'montant_engage', 'montant_execute', 'montant_decaisse',
        'statut', 'actif',
    ];

    protected $casts = [
        'date_signature' => 'date',
        'date_debut' => 'date',
        'date_fin' => 'date',
        'montant_accord' => 'decimal:2',
        'montant_engage' => 'decimal:2',
        'montant_execute' => 'decimal:2',
        'montant_decaisse' => 'decimal:2',
        'actif' => 'boolean',
    ];

    public function partenaire(): BelongsTo
    {
        return $this->belongsTo(PartenaireFinancier::class, 'partenaire_id');
    }

    public function sourceFinancement(): BelongsTo
    {
        return $this->belongsTo(SourceFinancement::class);
    }

    public function exercice(): BelongsTo
    {
        return $this->belongsTo(Exercice::class);
    }

    public function decaissements(): HasMany
    {
        return $this->hasMany(DecaissementConvention::class, 'convention_id')->latest('date_decaissement');
    }

    /** Reliquat = montant de l'accord non encore décaissé. */
    protected function reliquat(): Attribute
    {
        return Attribute::make(
            get: fn () => max((float) $this->montant_accord - (float) $this->montant_decaisse, 0),
        );
    }

    /** Taux de mobilisation = décaissé / accord. */
    protected function tauxMobilisation(): Attribute
    {
        return Attribute::make(
            get: fn () => (float) $this->montant_accord > 0
                ? round((float) $this->montant_decaisse / (float) $this->montant_accord * 100, 1)
                : 0.0,
        );
    }

    /** Recalcule le décaissé depuis les décaissements exécutés (source unique). */
    public function recalculerDecaisse(): void
    {
        $this->montant_decaisse = (float) $this->decaissements()->where('statut', 'execute')->sum('montant_xaf');
        $this->save();
    }
}
