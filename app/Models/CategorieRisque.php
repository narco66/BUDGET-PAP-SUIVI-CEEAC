<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CategorieRisque extends Model
{
    use Auditable;

    protected $table = 'categories_risques';

    protected $fillable = ['code', 'libelle', 'description', 'ordre'];

    public function risques(): HasMany
    {
        return $this->hasMany(Risque::class, 'categorie_risque_id');
    }
}
