<?php



namespace App\Models;



use App\Models\Strategie\ProgrammationAnnuelle;

use App\Models\Strategie\ProgrammationPluriannuelle;

use Illuminate\Database\Eloquent\Model;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

use Illuminate\Database\Eloquent\Relations\HasMany;



/** Acte de révision budgétaire — rectificatif ou virement (phase B4). */

class BudgetRevision extends Model

{

    public const TYPES = [

        'rectificatif' => 'Rectificatif',

        'virement' => 'Virement',

    ];



    protected $fillable = [

        'budget_id', 'reference', 'type', 'motif', 'montant_total', 'statut',

        'programmation_pluriannuelle_id', 'programmation_annuelle_id',

        'valide_le', 'valide_par', 'cree_par',

    ];



    protected $casts = [

        'montant_total' => 'decimal:2',

        'valide_le' => 'datetime',

    ];



    public function budget(): BelongsTo

    {

        return $this->belongsTo(Budget::class);

    }



    public function mouvements(): HasMany

    {

        return $this->hasMany(BudgetRevisionMouvement::class);

    }



    public function programmationPluriannuelle(): BelongsTo

    {

        return $this->belongsTo(ProgrammationPluriannuelle::class);

    }



    public function programmationAnnuelle(): BelongsTo

    {

        return $this->belongsTo(ProgrammationAnnuelle::class);

    }



    public function validateur(): BelongsTo

    {

        return $this->belongsTo(User::class, 'valide_par');

    }



    public function createur(): BelongsTo

    {

        return $this->belongsTo(User::class, 'cree_par');

    }



    public function estModifiable(): bool

    {

        return $this->statut === 'brouillon';

    }

}

