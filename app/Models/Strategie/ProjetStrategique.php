<?php

namespace App\Models\Strategie;

use App\Models\Concerns\Auditable;
use App\Models\Pilier;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProjetStrategique extends Model
{
    use SoftDeletes, Auditable;

    protected $table = 'projets';

    protected $fillable = [
        'programme_strategique_id', 'code', 'intitule', 'responsable_user_id',
        'statut', 'cycle_vie', 'date_debut', 'date_fin',
        'budget_previsionnel', 'budget_engage', 'budget_consomme',
        'pilier_id', 'description', 'created_by', 'updated_by',
    ];

    protected $casts = [
        'date_debut' => 'date',
        'date_fin' => 'date',
        'budget_previsionnel' => 'decimal:2',
        'budget_engage' => 'decimal:2',
        'budget_consomme' => 'decimal:2',
    ];

    public function programmeStrategique(): BelongsTo
    {
        return $this->belongsTo(ProgrammeStrategique::class);
    }

    public function responsable(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsable_user_id');
    }

    public function pilier(): BelongsTo
    {
        return $this->belongsTo(Pilier::class);
    }

    public function activites(): HasMany
    {
        return $this->hasMany(\App\Models\Activite::class, 'projet_id');
    }

    public function risques(): HasMany
    {
        return $this->hasMany(RisqueStrategique::class, 'projet_id');
    }

    public function portefeuilles(): BelongsToMany
    {
        return $this->belongsToMany(
            PortefeuilleProjet::class,
            'portefeuille_projet',
            'projet_id',
            'portefeuille_projet_id'
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
