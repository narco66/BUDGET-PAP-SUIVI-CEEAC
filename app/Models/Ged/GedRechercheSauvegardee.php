<?php

namespace App\Models\Ged;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GedRechercheSauvegardee extends Model
{
    protected $table = 'ged_recherches_sauvegardees';

    protected $fillable = ['utilisateur_id', 'nom', 'filtres', 'par_defaut'];

    protected function casts(): array
    {
        return [
            'filtres' => 'array',
            'par_defaut' => 'boolean',
        ];
    }

    public function utilisateur(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
