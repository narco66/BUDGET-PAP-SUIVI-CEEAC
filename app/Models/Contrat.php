<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasGedDocuments;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/** Contrat (rattaché à un marché et/ou un engagement). */
class Contrat extends Model
{
    use Auditable, SoftDeletes, HasGedDocuments;

    protected $table = 'contrats';

    protected $fillable = [
        'numero', 'exercice_id', 'marche_id', 'engagement_id', 'structure_id',
        'objet', 'titulaire', 'type_contrat', 'montant', 'date_signature',
        'date_debut', 'date_fin', 'statut', 'observations',
    ];

    protected $casts = [
        'montant' => 'decimal:2',
        'date_signature' => 'date',
        'date_debut' => 'date',
        'date_fin' => 'date',
    ];

    public const TYPES = [
        'marche' => 'Marché', 'convention' => 'Convention',
        'bon_commande' => 'Bon de commande', 'prestation' => 'Contrat de prestation',
    ];

    public const STATUTS = [
        'projet' => 'Projet', 'signe' => 'Signé', 'en_cours' => 'En cours',
        'execute' => 'Exécuté', 'resilie' => 'Résilié', 'suspendu' => 'Suspendu',
    ];

    public function exercice(): BelongsTo
    {
        return $this->belongsTo(Exercice::class);
    }

    public function marche(): BelongsTo
    {
        return $this->belongsTo(Marche::class);
    }

    public function engagement(): BelongsTo
    {
        return $this->belongsTo(Engagement::class);
    }

    public function structure(): BelongsTo
    {
        return $this->belongsTo(Structure::class);
    }
}
