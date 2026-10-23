<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VisaElectronique extends Model
{
    public $timestamps = false;

    protected $table = 'visas_electroniques';

    protected $fillable = [
        'workflow_instance_id', 'workflow_instance_etape_id', 'type_visa',
        'acteur_id', 'hash_contenu', 'certificat', 'ip', 'user_agent', 'created_at',
    ];

    protected $casts = ['created_at' => 'datetime'];

    public function instance(): BelongsTo
    {
        return $this->belongsTo(WorkflowInstance::class, 'workflow_instance_id');
    }

    public function etapeInstance(): BelongsTo
    {
        return $this->belongsTo(WorkflowInstanceEtape::class, 'workflow_instance_etape_id');
    }

    public function acteur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'acteur_id');
    }
}
