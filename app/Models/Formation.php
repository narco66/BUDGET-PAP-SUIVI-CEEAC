<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasGedDocuments;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/** Atelier / formation : participation, coûts, répartition genre, impact. */
class Formation extends Model
{
    use Auditable, SoftDeletes, HasGedDocuments;

    protected $table = 'formations';

    protected $fillable = [
        'numero', 'exercice_id', 'structure_id', 'source_financement_id', 'engagement_id',
        'intitule', 'type', 'thematique', 'lieu', 'date_debut', 'date_fin',
        'nb_participants', 'nb_hommes', 'nb_femmes', 'cout', 'niveau_satisfaction',
        'impact', 'statut', 'observations',
    ];

    protected $casts = [
        'date_debut' => 'date',
        'date_fin' => 'date',
        'nb_participants' => 'integer',
        'nb_hommes' => 'integer',
        'nb_femmes' => 'integer',
        'cout' => 'decimal:2',
        'niveau_satisfaction' => 'decimal:2',
    ];

    public const TYPES = [
        'formation' => 'Formation', 'atelier' => 'Atelier',
        'seminaire' => 'Séminaire', 'conference' => 'Conférence',
    ];

    public const STATUTS = ['planifie' => 'Planifié', 'realise' => 'Réalisé', 'annule' => 'Annulé'];

    public function exercice(): BelongsTo
    {
        return $this->belongsTo(Exercice::class);
    }

    public function structure(): BelongsTo
    {
        return $this->belongsTo(Structure::class);
    }

    public function sourceFinancement(): BelongsTo
    {
        return $this->belongsTo(SourceFinancement::class);
    }

    public function engagement(): BelongsTo
    {
        return $this->belongsTo(Engagement::class);
    }
}
