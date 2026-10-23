<?php

namespace App\Models;

use App\Models\Strategie\ProgrammationAnnuelle;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Pivot alignement granulaire activité PAP ↔ programmation annuelle (P4). */
class PapActiviteProgrammation extends Model
{
    protected $fillable = ['programmation_annuelle_id', 'activite_id', 'mode'];

    public function programmation(): BelongsTo
    {
        return $this->belongsTo(ProgrammationAnnuelle::class, 'programmation_annuelle_id');
    }

    public function activite(): BelongsTo
    {
        return $this->belongsTo(Activite::class);
    }
}
