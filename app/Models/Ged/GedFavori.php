<?php

namespace App\Models\Ged;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GedFavori extends Model
{
    protected $table = 'ged_favoris';

    protected $fillable = ['document_id', 'utilisateur_id'];

    public function document(): BelongsTo
    {
        return $this->belongsTo(GedDocument::class, 'document_id');
    }

    public function utilisateur(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
