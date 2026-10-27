<?php

namespace App\Models;

use App\Models\Concerns\AjoutSeul;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Lot archivé du journal (fichier hors base, empreinte, scellement de rattachement — JNL-019). */
class AuditArchive extends Model
{
    use AjoutSeul;

    public $timestamps = false;

    protected $table = 'audit_archives';

    protected $guarded = ['id'];

    protected $casts = ['nombre' => 'integer'];

    public function auteur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cree_par');
    }
}
