<?php

namespace App\Models\Ged;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/** Article wiki ou procédure institutionnelle (P3-3). */
class Connaissance extends Model
{
    use SoftDeletes;

    public const TYPES = [
        'wiki' => 'Article wiki',
        'procedure' => 'Procédure',
    ];

    public const STATUTS = [
        'brouillon' => 'Brouillon',
        'publie' => 'Publié',
        'archive' => 'Archivé',
    ];

    public const MODULES = [
        'general' => 'Général',
        'budget' => 'Budget & finance',
        'pap' => 'PAP / RBM',
        'depense' => 'Chaîne de dépense',
        'recettes' => 'Recettes',
        'ged' => 'GED',
        'workflow' => 'Workflow',
        'reporting' => 'Reporting',
        'suivi' => 'Suivi-évaluation',
        'securite' => 'Sécurité & accès',
    ];

    protected $fillable = [
        'reference', 'slug', 'titre', 'type', 'module', 'categorie', 'resume', 'contenu',
        'statut', 'version', 'auteur_id', 'publie_par_id', 'publie_le', 'tags', 'ged_document_id',
    ];

    protected function casts(): array
    {
        return [
            'tags' => 'array',
            'publie_le' => 'datetime',
            'version' => 'integer',
        ];
    }

    public function auteur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'auteur_id');
    }

    public function publiePar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'publie_par_id');
    }

    public function documentGed(): BelongsTo
    {
        return $this->belongsTo(GedDocument::class, 'ged_document_id');
    }

    public function versions(): HasMany
    {
        return $this->hasMany(ConnaissanceVersion::class)->orderByDesc('version');
    }

    /** Visibilité selon statut et droits GED. */
    public function scopeVisiblesPour(Builder $q, User $user): Builder
    {
        if ($user->can('ged.admin')) {
            return $q;
        }

        return $q->where(function (Builder $sub) use ($user) {
            $sub->where('statut', 'publie')
                ->orWhere(function (Builder $b) use ($user) {
                    $b->where('auteur_id', $user->id)->whereIn('statut', ['brouillon', 'publie']);
                });
        });
    }

    public function estModifiablePar(User $user): bool
    {
        return $user->can('ged.admin')
            || ($user->can('ged.modifier') && ($this->auteur_id === $user->id || $this->statut !== 'archive'));
    }
}
