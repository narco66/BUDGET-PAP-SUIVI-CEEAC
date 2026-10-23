<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class IncidentCategorie extends Model
{
    use Auditable;

    protected $table = 'incident_categories';

    protected $fillable = ['code', 'libelle', 'description', 'ordre'];

    public function incidents(): HasMany
    {
        return $this->hasMany(Incident::class, 'incident_categorie_id');
    }
}
