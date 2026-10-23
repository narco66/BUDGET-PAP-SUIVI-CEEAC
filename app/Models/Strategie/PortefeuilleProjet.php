<?php

namespace App\Models\Strategie;

use App\Models\Concerns\Auditable;
use App\Models\Exercice;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class PortefeuilleProjet extends Model
{
    use SoftDeletes, Auditable;

    protected $table = 'portefeuilles_projets';

    protected $fillable = [
        'reference', 'intitule', 'exercice_id', 'responsable_user_id',
        'statut', 'description', 'created_by', 'updated_by',
    ];

    public function exercice(): BelongsTo
    {
        return $this->belongsTo(Exercice::class);
    }

    public function responsable(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsable_user_id');
    }

    public function projets(): BelongsToMany
    {
        return $this->belongsToMany(
            ProjetStrategique::class,
            'portefeuille_projet',
            'portefeuille_projet_id',
            'projet_id'
        )->withPivot(['priorite', 'categorie', 'source_financement_id'])->withTimestamps();
    }

    public function createur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function modificateur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
