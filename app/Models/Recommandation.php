<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasGedDocuments;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/** Recommandation institutionnelle et son suivi d'exécution. */
class Recommandation extends Model
{
    use Auditable, SoftDeletes, HasGedDocuments;

    protected $table = 'recommandations';

    protected $fillable = [
        'numero', 'exercice_id', 'evaluation_periodique_id', 'structure_id', 'responsable_user_id',
        'source', 'categorie', 'reference_reunion',
        'date_emission', 'libelle', 'description', 'priorite', 'echeance', 'statut', 'taux_execution', 'observations',
    ];

    protected $casts = [
        'date_emission' => 'date',
        'echeance' => 'date',
        'taux_execution' => 'decimal:2',
    ];

    public const SOURCES = [
        'conference_chefs_etat' => 'Conférence des Chefs d’État',
        'conseil_ministres' => 'Conseil des Ministres',
        'reunion_statutaire' => 'Réunion statutaire',
        'mission_audit' => 'Mission d’audit',
        'autre' => 'Autre',
    ];

    public const CATEGORIES = [
        'gouvernance' => 'Gouvernance',
        'technique' => 'Technique',
        'financier' => 'Financier',
        'rh' => 'Ressources humaines',
        'institutionnel' => 'Institutionnel',
        'autre' => 'Autre',
    ];

    public const PRIORITES = ['haute' => 'Haute', 'moyenne' => 'Moyenne', 'basse' => 'Basse'];

    public const STATUTS = [
        'en_attente' => 'En attente', 'en_cours' => 'En cours', 'realisee' => 'Réalisée',
        'non_realisee' => 'Non réalisée', 'abandonnee' => 'Abandonnée',
    ];

    public function exercice(): BelongsTo
    {
        return $this->belongsTo(Exercice::class);
    }

    public function evaluationPeriodique(): BelongsTo
    {
        return $this->belongsTo(EvaluationPeriodique::class, 'evaluation_periodique_id');
    }

    public function structure(): BelongsTo
    {
        return $this->belongsTo(Structure::class);
    }

    public function responsable(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsable_user_id');
    }
}
