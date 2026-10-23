<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasGedDocuments;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

/** Marché public (élément du Plan de Passation des Marchés). */
class Marche extends Model
{
    use Auditable, SoftDeletes, HasGedDocuments;

    protected $table = 'marches';

    protected $fillable = [
        'numero', 'exercice_id', 'expression_besoin_id', 'engagement_id', 'structure_id',
        'objet', 'type_marche', 'mode_passation', 'montant_estime', 'montant_attribue',
        'attributaire', 'date_lancement', 'date_attribution', 'statut', 'observations',
    ];

    protected $casts = [
        'montant_estime' => 'decimal:2',
        'montant_attribue' => 'decimal:2',
        'date_lancement' => 'date',
        'date_attribution' => 'date',
    ];

    public const MODES = [
        'appel_offres_ouvert' => 'Appel d’offres ouvert',
        'appel_offres_restreint' => 'Appel d’offres restreint',
        'consultation' => 'Consultation',
        'gre_a_gre' => 'Gré à gré',
        'demande_cotation' => 'Demande de cotation',
    ];

    public const TYPES = [
        'fournitures' => 'Fournitures',
        'services' => 'Services',
        'travaux' => 'Travaux',
        'prestations_intellectuelles' => 'Prestations intellectuelles',
    ];

    public const STATUTS = [
        'planifie' => 'Planifié', 'lance' => 'Lancé', 'en_evaluation' => 'En évaluation',
        'attribue' => 'Attribué', 'signe' => 'Signé', 'execute' => 'Exécuté',
        'resilie' => 'Résilié', 'infructueux' => 'Infructueux',
    ];

    public function exercice(): BelongsTo
    {
        return $this->belongsTo(Exercice::class);
    }

    public function expressionBesoin(): BelongsTo
    {
        return $this->belongsTo(ExpressionBesoin::class, 'expression_besoin_id');
    }

    public function engagement(): BelongsTo
    {
        return $this->belongsTo(Engagement::class);
    }

    public function structure(): BelongsTo
    {
        return $this->belongsTo(Structure::class);
    }

    public function contrat(): HasOne
    {
        return $this->hasOne(Contrat::class);
    }
}
