<?php

namespace App\Models;

use App\Models\Concerns\AjoutSeul;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Trace d'un export du journal (acteur, périmètre, filtres, empreinte du fichier — JNL-013). */
class AuditExport extends Model
{
    use AjoutSeul;

    public $timestamps = false;

    protected $table = 'audit_exports';

    protected $guarded = ['id'];

    protected $casts = ['filtres' => 'array'];

    public function auteur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
