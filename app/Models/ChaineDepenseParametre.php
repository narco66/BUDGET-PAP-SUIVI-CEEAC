<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChaineDepenseParametre extends Model
{
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
