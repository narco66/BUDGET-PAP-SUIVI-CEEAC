<?php

namespace App\Models\Ged;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Permission\Models\Role;

class GedWorkflowEtape extends Model
{
    protected $table = 'ged_workflow_etapes';

    protected $fillable = [
        'workflow_id', 'ordre', 'code', 'libelle', 'type_etape', 'role_id',
        'utilisateur_id', 'delai_jours', 'notification',
    ];

    protected function casts(): array
    {
        return ['notification' => 'boolean'];
    }

    public function workflow(): BelongsTo
    {
        return $this->belongsTo(GedWorkflow::class, 'workflow_id');
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function utilisateur(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
