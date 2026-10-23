<?php

namespace App\Models\Ged;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConnaissanceVersion extends Model
{
    public $timestamps = false;

    protected $fillable = ['connaissance_id', 'version', 'contenu', 'auteur_id', 'created_at'];

    protected function casts(): array
    {
        return ['created_at' => 'datetime', 'version' => 'integer'];
    }

    public function connaissance(): BelongsTo
    {
        return $this->belongsTo(Connaissance::class);
    }

    public function auteur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'auteur_id');
    }
}
