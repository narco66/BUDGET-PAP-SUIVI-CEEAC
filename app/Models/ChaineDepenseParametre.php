<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChaineDepenseParametre extends Model
{
    use Auditable;

    protected $table = 'chaine_depense_parametres';

    protected $fillable = ['cle', 'valeur', 'version', 'juridiquement_valide', 'commentaire', 'modifie_par'];

    protected $casts = [
        'juridiquement_valide' => 'boolean',
        'version' => 'integer',
    ];

    public function modificateur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'modifie_par');
    }
}
