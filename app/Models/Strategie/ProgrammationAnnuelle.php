<?php

namespace App\Models\Strategie;

use App\Models\Concerns\Auditable;
use App\Models\Exercice;
use App\Models\Pap;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProgrammationAnnuelle extends Model
{
    use SoftDeletes, Auditable;

    protected $table = 'programmations_annuelles';

    protected $fillable = [
        'programmation_pluriannuelle_id', 'exercice_id', 'pap_id', 'statut',
        'activites_prevues', 'ressources_prevues', 'indicateurs_prevus',
        'created_by', 'updated_by',
    ];

    public function programmationPluriannuelle(): BelongsTo
    {
        return $this->belongsTo(ProgrammationPluriannuelle::class);
    }

    public function exercice(): BelongsTo
    {
        return $this->belongsTo(Exercice::class);
    }

    public function pap(): BelongsTo
    {
        return $this->belongsTo(Pap::class);
    }

    public function createur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function modificateur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
