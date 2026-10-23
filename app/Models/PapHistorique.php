<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Entrée d'historique / snapshot du cycle de vie PAP. */
class PapHistorique extends Model
{
    protected $table = 'pap_historiques';

    protected $fillable = ['pap_id', 'version', 'action', 'snapshot', 'snapshot_complet', 'motif', 'user_id'];

    protected $casts = ['snapshot' => 'array', 'snapshot_complet' => 'boolean'];

    public function pap(): BelongsTo
    {
        return $this->belongsTo(Pap::class);
    }

    public function auteur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
