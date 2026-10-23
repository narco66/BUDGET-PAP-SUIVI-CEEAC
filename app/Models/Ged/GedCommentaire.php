<?php

namespace App\Models\Ged;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class GedCommentaire extends Model
{
    use SoftDeletes;

    protected $table = 'ged_commentaires';

    protected $fillable = ['document_id', 'utilisateur_id', 'contenu', 'est_interne'];

    protected function casts(): array
    {
        return ['est_interne' => 'boolean'];
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(GedDocument::class, 'document_id');
    }

    public function utilisateur(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
