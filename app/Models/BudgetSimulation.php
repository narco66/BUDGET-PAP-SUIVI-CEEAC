<?php



namespace App\Models;



use Illuminate\Database\Eloquent\Model;

use Illuminate\Database\Eloquent\Relations\BelongsTo;



/** Scénario budgétaire what-if comparable (phase B3). */

class BudgetSimulation extends Model

{

    public const TYPES = [

        'global_pct' => 'Variation globale (%)',

        'structure_pct' => 'Variation par structure (%)',

        'enveloppe_plafond' => 'Plafond enveloppe',

        'ligne_ajustements' => 'Ajustements par ligne',

    ];



    protected $fillable = [

        'budget_id', 'code', 'libelle', 'hypothese', 'type',

        'parametres', 'resultat', 'montant_base', 'montant_simule',

        'ecart_absolu', 'ecart_pct', 'statut', 'cree_par', 'calcule_le',

    ];



    protected $casts = [

        'parametres' => 'array',

        'resultat' => 'array',

        'montant_base' => 'decimal:2',

        'montant_simule' => 'decimal:2',

        'ecart_absolu' => 'decimal:2',

        'ecart_pct' => 'decimal:4',

        'calcule_le' => 'datetime',

    ];



    public function budget(): BelongsTo

    {

        return $this->belongsTo(Budget::class);

    }



    public function createur(): BelongsTo

    {

        return $this->belongsTo(User::class, 'cree_par');

    }



    public function estCalculee(): bool

    {

        return in_array($this->statut, ['calcule', 'retenu'], true);

    }

}

