<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class PapMesureCorrective extends Model
{
    use SoftDeletes, Auditable;

    protected $table = 'pap_mesures_correctives';

    protected $fillable = [
        'pap_id', 'pap_risque_id', 'activite_id', 'code', 'description',
        'echeance', 'statut', 'resultat', 'responsable_user_id',
    ];

    protected $casts = ['echeance' => 'date'];

    public function pap(): BelongsTo
    {
        return $this->belongsTo(Pap::class);
    }

    public function risque(): BelongsTo
    {
        return $this->belongsTo(PapRisque::class, 'pap_risque_id');
    }

    public function activite(): BelongsTo
    {
        return $this->belongsTo(Activite::class);
    }

    public function responsable(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsable_user_id');
    }
}
